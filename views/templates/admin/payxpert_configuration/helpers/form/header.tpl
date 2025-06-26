<div class="configuration-header">
    <img width="100%" class="banner-img" src="{$module_dir}payxpert/views/img/banners/PayXpertBanner.jpg" alt="PayXpert Banner" data-target="https://www.payxpert.fr/contactez-nous/"/>
</div>

<div class="configuration-block">
    <div class="configuration-information">
        <div class="panel">
            <h3><i class="icon icon-bug"></i> {l s='CMS Configuration Information' mod='payxpert'}</h3>
            <ul class="debug-grid">
                <li><strong>{l s='PHP Version:' mod='payxpert'}</strong> {$moduleDebugInfo['{php_version}']}</li>
                <li><strong>{l s='CMS Version:' mod='payxpert'}</strong> {$moduleDebugInfo['{cms_name}']} {$moduleDebugInfo['{cms_version}']}</li>
                <li><strong>{l s='Module Version:' mod='payxpert'}</strong> {$moduleDebugInfo['{module_version}']}</li>
                <li><strong>{l s='Override in /override:' mod='payxpert'}</strong>
                    {if $moduleDebugInfo['{is_overridden_in_override}']}
                        ✅ {l s='Yes' d='Admin.Global'}
                    {else}
                        ❌ {l s='No' d='Admin.Global'}
                    {/if}
                </li>
                <li><strong>{l s='Override in /theme:' mod='payxpert'}</strong>
                    {if $moduleDebugInfo['{is_overridden_in_theme}']}
                        ✅ {l s='Yes' d='Admin.Global'}
                    {else}
                        ❌ {l s='No' d='Admin.Global'}
                    {/if}
                </li>
                <li><strong>{l s='API keys valid:' mod='payxpert'}</strong>
                    {if $moduleDebugInfo['{is_key_valid}']}
                        ✅ {l s='Yes' d='Admin.Global'}
                    {else}
                        ❌ {l s='No' d='Admin.Global'}
                    {/if}
                </li>
            </ul>
        </div>
    </div>

    <div class="support">
        <h1>{l s='Support' mod='payxpert'}</h1>
        <button id="askSupportBtn" data-toggle="modal" data-target="#payxpert_modal_support" class="btn btn-primary">
            {l s='Contact Us' mod='payxpert'}
        </button>
        <a class="btn btn-default" href="{$downloadUrl|escape:'htmlall':'UTF-8'}">
            {l s='Download LOGs' mod='payxpert'}
        </a>
    </div>
</div>
