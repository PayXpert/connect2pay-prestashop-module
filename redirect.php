<?php
/**
* Copyright 2013-2018 PayXpert
*
*   Licensed under the Apache License, Version 2.0 (the "License");
*   you may not use this file except in compliance with the License.
*   You may obtain a copy of the License at
*
*      http://www.apache.org/licenses/LICENSE-2.0
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
*/

/* Used for Prestashop < 1.5 */

/* SSL Management */
$useSSL = true;

require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../header.php');
require_once(dirname(__FILE__) . '/payxpert.php');

if (!isset($cookie)) {
    $cookie = $this->context->cookie;
}

if (!isset($cart)) {
    $cart = $this->context->cart;
}

if (!$cookie->isLogged()) {
    Tools::redirect('authentication.php?back=order.php');
}

$payxpert = new PayXpert();
$message = $payxpert->redirect($cart);

echo $payxpert->displayErrorPage($message);

require_once(dirname(__FILE__) . '/../../footer.php');
