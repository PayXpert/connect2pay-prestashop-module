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

{extends "$layout"}
{block name="content"}
  <a href="{$this_link_back|escape:'html'}" class="button_large">{l s='Other payment methods' mod='payxpert'}</a>
  <div class="fluidIframe">
    <iframe src="{$src|escape:'htmlall':'UTF-8'}" id="connect2pay-iframe" width="900" height="400"></iframe>
  </div>
{/block}