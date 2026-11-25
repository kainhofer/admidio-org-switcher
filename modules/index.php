<?php
/**
 ***********************************************************************************************
 * Organization switcher - the organizations of the user, and the switch to one of them.
 *
 * Parameters:
 * mode : dialog (the content of the dialog the profile menu opens), list (the same as a page, for a
 *        browser without JavaScript), switch (POST, with organization = short name)
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\OrganizationSwitcher\OrganizationSwitcher;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    $plugin = PluginRegistry::get(OrganizationSwitcher::ID);
    $switcher = new OrganizationSwitcher($gDb);
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('dialog', 'list', 'switch'), 'defaultValue' => 'list'));

    if ($getMode === 'switch') {
        SecurityUtils::validateCsrfToken((string)($_POST['adm_csrf_token'] ?? ''));
        $switcher->switchTo((string)($_POST['organization'] ?? ''));
        admRedirect(ADMIDIO_URL . FOLDER_MODULES . '/overview.php');
    }

    $organizations = array_values(array_filter(
        $switcher->getOrganizations((int)$gCurrentUserId, (int)$gCurrentOrgId),
        static fn(array $organization): bool => $organization['member']
    ));
    $switchUrl = SecurityUtils::encodeUrl($plugin->getUrl('index.php'), array('mode' => 'switch'));

    if ($getMode === 'dialog') {
        $smarty = PagePresenter::createSmartyObject();
        $smarty->addTemplateDir($plugin->getDirectory(Plugin::DIR_TEMPLATES));
        $smarty->assign('l10n', $gL10n);
        $smarty->assign('organizations', $organizations);
        $smarty->assign('switchUrl', $switchUrl);
        $smarty->assign('csrfToken', $gCurrentSession->getCsrfToken());
        echo $smarty->fetch('plugin.organization-switcher.dialog.tpl');
        exit();
    }

    $page = PagePresenter::withHtmlIDAndHeadline('adm_plg_organization_switcher', $gL10n->get('PLG_ORGANIZATION_SWITCHER_SWITCH'));
    $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
    $page->addTemplateFolder($plugin->getDirectory(Plugin::DIR_TEMPLATES));
    $page->addTemplateFile('plugin.organization-switcher.tpl');
    $page->assignSmartyVariable('organizations', $organizations);
    $page->assignSmartyVariable('switchUrl', $switchUrl);
    $page->assignSmartyVariable('csrfToken', $gCurrentSession->getCsrfToken());
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
