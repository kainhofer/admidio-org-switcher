{* One form for all organizations: the button that was clicked sends its organization. The entries are
   list items and no .btn, so they keep the colors of the list whatever the theme does with buttons. *}
<form method="post" action="{$switchUrl}">
    <input type="hidden" name="adm_csrf_token" value="{$csrfToken}">
    <div class="list-group">
        {foreach $organizations as $org}
            {if $org.current}
                <div class="list-group-item active" aria-current="true">
                    <strong>{$org.longname|escape}</strong> <small>({$org.shortname|escape})</small>
                    <span class="badge text-bg-light ms-2">{$l10n->get('PLG_ORGANIZATION_SWITCHER_CURRENT')}</span>
                </div>
            {else}
                <button type="submit" name="organization" value="{$org.shortname|escape}" class="list-group-item list-group-item-action">
                    <i class="bi bi-arrow-left-right me-2"></i><strong>{$org.longname|escape}</strong> <small class="text-body-secondary">({$org.shortname|escape})</small>
                </button>
            {/if}
        {/foreach}
    </div>
</form>
