<div class="modal-header">
    <h3 class="modal-title"><i class="bi bi-arrow-left-right"></i> {$l10n->get('PLG_ORG_SWITCHER_SWITCH')}</h3>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{$l10n->get('SYS_CLOSE')}"></button>
</div>
<div class="modal-body">
    <p>{$l10n->get('PLG_ORG_SWITCHER_DESC')}</p>
    {include file='plugin.org-switcher.list.tpl'}
</div>
