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

{extends file=$layout}

{block name='content'}
  <div class="container-fluid">
    <div class="row">
      <h2>{l s='Order confirmation' mod='payxpert'}</h2>
    </div>
    {if $nbProducts <= 0}
      <p class="alert alert-warning">{l s='Your shopping cart is empty.' mod='payxpert'}</p>
    {else}
      <div class="row">
        {assign var='current_step' value='payment'}
        <div class="col-xs-12 col-md-2">
          <img src="{$this_path}images/payment-types/{$payment_logo}.png" alt="{l s='Bank Transfer' mod='payxpert'}">
        </div>
        <div class="col-xs-12 col-md-10">
          <strong class="dark">
            {l s='You have chosen to pay by Bank Transfer.' mod='payxpert'}<br>
          </strong>
          <p>
            {capture name='amount'}{Tools::displayPrice($total, $cust_currency)}{/capture}
            {assign var='order_amount' value=$smarty.capture.amount}
            {l s='The total amount of your order is %s.' sprintf=[$order_amount|escape:'html':'UTF-8'] mod='payxpert'}
            <br>
            {l s='You will be able to pay by entering your bank account information on the next pages using a secured payment form.' mod='payxpert'}
          </p>
          <p>
            <b>{l s='Please confirm your order by clicking the "Pay my order" button below.' mod='payxpert'}</b>
          </p>
          <div class="row">
            <div class="col-xs-12 col-md-6">
              <form action="{$this_link|escape:'html'}" method="post">    
                <button class="button btn btn-primary button-medium" type="submit">
                  <span>{l s='Pay my order' mod='payxpert'}<i class="icon-chevron-right right"></i></span>
                </button>
              </form>
            </div>
            <div class="col-xs-12 col-md-6">
                <a class="button-exclusive btn btn-default" href="{$this_link_back|escape:'html':'UTF-8'}">
                  <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='payxpert'}
                </a>
            </div>
          </div>
        </div>
      </div>
    {/if}
  </div>
{/block}