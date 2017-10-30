{*
* Copyright 2013-2017 PayXpert
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
*  limitations under the License. 
*   
*  @author Regis Vidal
*
*}
{capture name=path}{l s='Credit Card payment.' mod='payxpert'}{/capture}
{if $smarty.const._PS_VERSION_ < 1.6}{include file="$tpl_dir./breadcrumb.tpl"}{/if} 
<h1>{l s='Order summary' mod='payxpert'}</h1>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $smarty.const._PS_VERSION_ >= 1.6}
  <div class="box cheque-box">
    <h3 class="page-subheading">Error</h3>
{else}
{if $smarty.const._PS_VERSION_ >= 1.5 && version_compare(_PS_VERSION_, '1.6', '<') }
<style>
  #module-payxpert-redirect #center_column {
    width: 757px;
  }
</style>
{/if} 
<div class="paiement_block">
 <h3>Error</h3>
{/if} 
  <h4>{$errorMessage}</h4>
{if $smarty.const._PS_VERSION_ >= 1.6}
  </div><!-- .cheque-box -->
  <p class="cart_navigation clearfix" id="cart_navigation">
   <a href="{$this_link_back|escape:'html':'UTF-8'}" class="button-exclusive btn btn-default">
      <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='payxpert'}
   </a>
  </p>
{else}
<p class="cart_navigation" id="cart_navigation">
  <a href="{$this_link_back|escape:'html'}" class="button_large">{l s='Other payment methods' mod='payxpert'}</a>
</p>
</div>
{/if}