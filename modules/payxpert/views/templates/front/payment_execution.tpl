{*
*   Copyright 2013-2017 PayXpert
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
*  @copyright 2013-2017 PayXpert
*  @license   http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0 (the "License")
*}

{capture name=path}{l s='Credit Card payment.' mod='payxpert'}{/capture}

{if $smarty.const._PS_VERSION_ < 1.6}
{include file="$tpl_dir./breadcrumb.tpl"}
{/if}
<h2>{l s='Order summary' mod='payxpert'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if $nbProducts <= 0}
  <p class="alert alert-warning">{l s='Your shopping cart is empty.' mod='payxpert'}</p>
{else}
  {if $smarty.const._PS_VERSION_ < 1.6}<h3>{l s='Credit Card payment.' mod='payxpert'}</h3>{/if}
    <form action="{$this_link|escape:'html'}" method="post">
       {if $smarty.const._PS_VERSION_ < 1.6}
         <p>
       {else}
          <div class="box cheque-box">
          <h3 class="page-subheading">{l s='Credit Card payment.' mod='payxpert'}</h3>
          <p class="cheque-indent">
           <strong class="dark">
        {/if}
            <img src="{$this_path}images/payment-types/creditcard.png" alt="{l s='Credit Card' mod='payxpert'}" style="float:left; margin: 0px 10px 5px 0px;" />
            {l s='You have chosen to pay by Credit Card.' mod='payxpert'}  {l s='Here is a short summary of your order:' mod='payxpert'}
        {if $smarty.const._PS_VERSION_ >= 1.6}</strong>{/if}
          <br/><br/>
        </p>
        <p style="margin-top:20px;">
            - {l s='The total amount of your order is' mod='payxpert'}
            <span id="amount" class="price">{Tools::displayPrice($total, $currency, false)}</span>
            {if $use_taxes == 1}
                {l s='(tax incl.)' mod='payxpert'}
            {/if}
        </p>
        <p>
          {l s='Credit Card information will be displayed on the next page using a secure payment page.' mod='payxpert'}
          <br /><br />
          <b>{l s='Please confirm your order by clicking "Place my order."' mod='payxpert'}.</b>
        </p>
        {if $smarty.const._PS_VERSION_ < 1.6}
          <p class="cart_navigation" id="cart_navigation">
	       <input type="submit" value="{l s='Place my order' mod='payxpert'}" class="exclusive_large" />
	       <a href="{$this_link_back|escape:'html'}" class="button_large">{l s='Other payment methods' mod='payxpert'}</a>
          </p>
        {else}
         </div><!-- .cheque-box -->
          <p class="cart_navigation clearfix" id="cart_navigation">
            <a class="button-exclusive btn btn-default" href="{$this_link_back|escape:'html':'UTF-8'}">
                <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='payxpert'}
            </a>
            <button class="button btn btn-default button-medium" type="submit">
                <span>{l s='Place my order' mod='payxpert'}<i class="icon-chevron-right right"></i></span>
            </button>
          </p>
        {/if}
    </form>
{/if}