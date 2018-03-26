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
*  @author Regis Vidal
*}
<form method="post">
    <fieldset>
        <legend><img src="../img/admin/contact.gif" />Payxpert - {l s='Settings' mod='payxpert'}</legend>
        
        <div class="clean">&nbsp;</div>
        <label for="PAYXPERT_ORIGINATOR">{l s='Originator ID' mod='payxpert'}</label>
        <div class="margin-form">
            <input type="text" id="PAYXPERT_ORIGINATOR" size="64" name="PAYXPERT_ORIGINATOR" value="{$PAYXPERT_ORIGINATOR}" />
            <p>{l s='The identifier of your Originator' mod='payxpert'}</p>
        </div>
        <div class="clean">&nbsp;</div>
        
        <label for="PAYXPERT_PASSWORD">{l s='Originator password' mod='payxpert'}</label>
        <div class="margin-form">
            <input type="password" id="PAYXPERT_PASSWORD" size="64" name="PAYXPERT_PASSWORD" />
            <p>{l s='The password associated with your Originator (leave empty to keep the current one)' mod='payxpert'}</p>
        </div>
        <div class="clean">&nbsp;</div>
        
        <label for="PAYXPERT_URL">{l s='Payment Gateway URL' mod='payxpert'}</label>
        <div class="margin-form">
            <input type="text" id="PAYXPERT_URL" size="64" name="PAYXPERT_URL" value="{$PAYXPERT_URL}" />
            <p>{l s='Leave this field empty unless you have been given an URL"' mod='payxpert'}</p>
        </div>
        <div class="clean">&nbsp;</div>
        
        <label for="PAYXPERT_MERCHANT_NOTIF">{l s='Merchant notifications' mod='payxpert'}</label>
        <div class="margin-form">
          <input type="checkbox" id="PAYXPERT_MERCHANT_NOTIF" name="PAYXPERT_MERCHANT_NOTIF"{if $PAYXPERT_MERCHANT_NOTIF eq 'true'} checked="true"{/if} />
          <!--<input type="hidden" name="PAYXPERT_MERCHANT_NOTIF" value="false" />-->
          <p>{l s='Whether or not to send a notification to the merchant for each processed payment' mod='payxpert'}</p>
        </div>
        <div class="clean">&nbsp;</div>
        
        <label for="PAYXPERT_MERCHANT_NOTIF_TO">{l s='Merchant notifications recipient' mod='payxpert'}</label>
        <div class="margin-form">
            <input type="text" id="PAYXPERT_MERCHANT_NOTIF_TO" size="64" name="PAYXPERT_MERCHANT_NOTIF_TO" value="{$PAYXPERT_MERCHANT_NOTIF_TO}" />
            <p>{l s='Recipient email address for merchant notifications' mod='payxpert'}</p>
        </div>
        <div class="clean">&nbsp;</div>
        
        <label for="PAYXPERT_MERCHANT_NOTIF_LANG">{l s='Merchant notifications lang' mod='payxpert'}</label>
        <div class="margin-form">
            <select id="PAYXPERT_MERCHANT_NOTIF_LANG" size="1" name="PAYXPERT_MERCHANT_NOTIF_LANG">
              <option value="en"{if $PAYXPERT_MERCHANT_NOTIF_LANG eq 'en'} selected="selected"{/if}>{l s='English' mod='payxpert'}</option>
              <option value="fr"{if $PAYXPERT_MERCHANT_NOTIF_LANG eq 'fr'} selected="selected"{/if}>{l s='French' mod='payxpert'}</option>
              <option value="es"{if $PAYXPERT_MERCHANT_NOTIF_LANG eq 'es'} selected="selected"{/if}>{l s='Spanish' mod='payxpert'}</option>
              <option value="it"{if $PAYXPERT_MERCHANT_NOTIF_LANG eq 'it'} selected="selected"{/if}>{l s='Italian' mod='payxpert'}</option>
            </select>
            <p>{l s='Language to use for merchant notifications' mod='payxpert'}</p>
        </div>
        <div class="clean">&nbsp;</div>
        
        <div class="margin-form">
          <input type="submit" name="btnSubmit" value="{l s='Update settings' mod='payxpert'}" class="button" />
        </div>
        <div class="clean">&nbsp;</div>
    </fieldset>
</form>
<div class="clean">&nbsp;</div>
