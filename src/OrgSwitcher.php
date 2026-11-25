<?php
namespace AdmidioPlugin\OrgSwitcher;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Session\Entity\AutoLogin;
use Admidio\Session\Entity\Session;
use Admidio\UI\Presenter\PagePresenter;
use ReflectionProperty;

/**
 ***********************************************************************************************
 * Switch the current session to another organization without logging out.
 *
 * Only organizations in which the user has an active membership are offered - the same ones the
 * login dialog would let them log in to. The switch sets the organization of the session and
 * discards the objects the session keeps (organization, preferences, profile fields, user, menu,
 * language), so the next request builds all of them anew for the new organization. With an
 * auto-login ("remember me") the auto-login record is moved to the new organization as well.
 *
 * The entry in the profile menu uses the filter page_user_menu_items of the core; a core without
 * it gets the entry put into the profile menu in the browser.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class OrgSwitcher
{
    public const ID = 'org-switcher';
    private const CORE_HOOKS = 'Admidio\\UI\\Component\\PageExtensions';

    /** @var array<int,array<string,mixed>>|null the organizations of the current user, per request */
    private static ?array $organizations = null;

    public function __construct(private readonly Database $database)
    {
    }

    public static function register(): void
    {
        if (class_exists(self::CORE_HOOKS)) {
            Hooks::addFilter('page_user_menu_items', array(self::class, 'addUserMenuItem'), Hooks::DEFAULT_PRIORITY, 1, self::ID);
        } else {
            Hooks::addAction('page_before_render', array(self::class, 'injectUserMenuItem'), Hooks::DEFAULT_PRIORITY, 1, self::ID);
        }
    }

    /**
     * Whether the current user may switch at all: logged in and member of more than one organization.
     */
    public static function isAvailable(): bool
    {
        global $gValidLogin, $gDb, $gCurrentUserId, $gCurrentOrgId;

        if (!$gValidLogin) {
            return false;
        }
        if (self::$organizations === null) {
            self::$organizations = (new self($gDb))->getOrganizations((int)$gCurrentUserId, (int)$gCurrentOrgId);
        }
        return count(array_filter(self::$organizations, static fn(array $organization): bool => $organization['member'])) > 1;
    }

    /**
     * The address of the dialog with the organizations of the user.
     */
    public static function getDialogUrl(): string
    {
        return SecurityUtils::encodeUrl(PluginRegistry::get(self::ID)->getUrl('index.php'), array('mode' => 'dialog'));
    }

    /**
     * Callback of **page_user_menu_items**.
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    public static function addUserMenuItem(array $items): array
    {
        if (self::isAvailable()) {
            $items[] = array(
                'id' => self::ID,
                'label' => 'PLG_ORG_SWITCHER_SWITCH',
                'icon' => 'bi-arrow-left-right',
                'url' => self::getDialogUrl(),
                'popup' => true
            );
        }
        return $items;
    }

    /**
     * Callback of **page_before_render** for a core without page_user_menu_items.
     */
    public static function injectUserMenuItem(PagePresenter $page): void
    {
        global $gL10n, $gLayoutReduced;

        if (!empty($gLayoutReduced) || !self::isAvailable()) {
            return;
        }
        $url = json_encode(self::getDialogUrl());
        $label = json_encode($gL10n->get('PLG_ORG_SWITCHER_SWITCH'));
        $page->addJavascript('
            var organizationSwitcherLink = $("<a class=\"nav-link link-primary openPopup\" href=\"javascript:void(0);\"></a>").attr("data-href", ' . $url . ')
                .append($("<i class=\"bi bi-arrow-left-right me-1\"></i>")).append(document.createTextNode(' . $label . '));
            $("#adm_dropdown_user_photo .dropdown-menu li:last").before($("<li class=\"nav-item mb-2\"></li>").append(organizationSwitcherLink));', true);
    }

    /**
     * All organizations, with the information whether the user is an active member.
     * @return array<int,array{id:int, shortname:string, longname:string, member:bool, current:bool}>
     */
    public function getOrganizations(int $userId, int $currentOrgId): array
    {
        $sql = 'SELECT org_id, org_shortname, org_longname,
                       (SELECT COUNT(*)
                          FROM ' . TBL_MEMBERS . '
                    INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
                    INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                         WHERE mem_usr_id = ?
                           AND mem_begin <= ?
                           AND mem_end > ?
                           AND rol_valid = true
                           AND (cat_org_id = org_id OR cat_org_id IS NULL)) AS memberships
                  FROM ' . TBL_ORGANIZATIONS . '
              ORDER BY org_longname, org_shortname';
        $statement = $this->database->queryPrepared($sql, array($userId, DATE_NOW, DATE_NOW));

        $organizations = array();
        while ($row = $statement->fetch()) {
            $organizations[] = array(
                'id' => (int)$row['org_id'],
                'shortname' => (string)$row['org_shortname'],
                'longname' => (string)$row['org_longname'],
                'member' => (int)$row['memberships'] > 0,
                'current' => (int)$row['org_id'] === $currentOrgId
            );
        }
        return $organizations;
    }

    /**
     * Switch the session of the current user to the organization.
     * @throws Exception if the user is no member there
     */
    public function switchTo(string $shortname): void
    {
        global $gCurrentSession, $gCurrentUserId, $gCurrentOrgId;

        $target = null;
        foreach ($this->getOrganizations((int)$gCurrentUserId, (int)$gCurrentOrgId) as $organization) {
            if ($organization['shortname'] === $shortname) {
                $target = $organization;
            }
        }
        if ($target === null || !$target['member']) {
            throw new Exception('PLG_ORG_SWITCHER_NOT_MEMBER');
        }
        if ($target['current']) {
            return;
        }

        $gCurrentSession->setValue('ses_org_id', $target['id']);
        $gCurrentSession->save();

        /*
         * A session of a "remember me" login takes its organization from the auto-login record
         * (Session::getOrganizationId()), and the next request would write that one back into the
         * session. The record moves along; the login dialog stores the chosen organization there
         * as well. The core offers no access to it, so it is read from the protected property.
         */
        $property = new ReflectionProperty(Session::class, 'mAutoLogin');
        $autoLogin = $property->isInitialized($gCurrentSession) ? $property->getValue($gCurrentSession) : null;
        if ($autoLogin instanceof AutoLogin && (int)$autoLogin->getValue('atl_id') > 0) {
            $autoLogin->setValue('atl_org_id', $target['id']);
            $autoLogin->save();
        }

        $gCurrentSession->initializeObjects();
    }
}
