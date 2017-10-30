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
{if $status == 'ok'}
	<p>{l s='Your order has been completed.' mod='payxpert'}
		<br /><br /><span class="bold">{l s='It will be shipped as soon as possible.' mod='payxpert'}</span>
		<br /><br />{l s='For any questions or for further information, please contact our' mod='payxpert'} <a href="{$this_link_contact}">{l s='customer support' mod='payxpert'}</a>.
	</p>
{else}
	{if $status == 'pending'}
		<p>{l s='Your order is still pending.' mod='payxpert'}
			<br /><br /><span class="bold">{l s='Your order will be shipped as soon as we receive your payment.' mod='payxpert'}</span>
			<br /><br />{l s='For any questions or for further information, please contact our' mod='payxpert'} <a href="{$this_link_contact}">{l s='customer support' mod='payxpert'}</a>.
		</p>
	{else}
		<p class="warning">
			{l s='We noticed a problem with your order. If you think this is an error, you can contact our' mod='payxpert'} 
			<a href="{$this_link_contact}">{l s='customer support' mod='payxpert'}</a>.
		</p>
	{/if}
{/if}