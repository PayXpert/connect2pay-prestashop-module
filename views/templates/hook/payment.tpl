{*
* Copyright 2013-2018 PayXpert
*
*   Licensed under the Apache License, Version 2.0 (the "License");
*   you may not use this file except in compliance with the License.
*   You may obtain a copy of the License at
*
*       http://www.apache.org/licenses/LICENSE-2.0
*
*   Unless required by applicable law or agreed to in writing, software
*   distributed under the License is distributed on an "AS IS" BASIS,
*   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
*   See the License for the specific language governing permissions and
*   limitations under the License. 
*   
*  @author    Regis Vidal
*  @copyright 2013-2018 PayXpert
*  @license   http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0 (the "License")
*}

{if $smarty.const._PS_VERSION_ >= 1.6}
  <div class="row">
    <div class="col-xs-12">
{/if}   
      <p class="payment_module">
        <a href="{$this_link|escape:'html'}" title="{l s='Pay by Credit Card' mod='payxpert'}" class="creditcard">
          {if $smarty.const._PS_VERSION_ < 1.6}
            <img src="{$this_path|escape:'htmlall':'UTF-8'}views/img/payment-types/creditcard.png" alt="{l s='Pay by Credit Card' mod='payxpert'}" width="86" height="86"/>
          {/if}
          {l s='Pay by Credit Card' mod='payxpert'}
        </a>
      </p>
{if $smarty.const._PS_VERSION_ >= 1.6}
    </div>
  </div>
{/if}