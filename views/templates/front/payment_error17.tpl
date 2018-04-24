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
*  limitations under the License. 
*   
*  @author Regis Vidal
*
*}
{capture name=path}{l s='Credit Card payment.' mod='payxpert'}{/capture}

{extends file='page.tpl'}
<h1>{l s='Order summary' mod='payxpert'}</h1>

{assign var='current_step' value='payment'}

{block name="content"}

  <style>
    #module-payxpert-redirect #center_column {
      width: 757px;
    }
  </style>

  <div class="paiement_block">
   <h3>Error</h3>

   <h4>{$errorMessage|escape:'htmlall':'UTF-8'}</h4>

  </div>
  
  <p class="cart_navigation clearfix" id="cart_navigation">
   <a href="{$this_link_back|escape:'html':'UTF-8'}" class="button-exclusive btn btn-default">
      {l s='Other payment methods' mod='payxpert'}
   </a>
  </p>
  
{/block}