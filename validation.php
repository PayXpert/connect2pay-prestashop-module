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
require_once(dirname(__FILE__) . '/../../init.php');
require_once(dirname(__FILE__) . '/payxpert.php');
require_once(dirname(__FILE__) . '/lib/Connect2PayClient.php');

$payxpert = new PayXpert();

// init api
$c2pClient = new PayXpert\Connect2Pay\Connect2PayClient(
    $payxpert->getPayXpertUrl(),
    Configuration::get('PAYXPERT_ORIGINATOR'),
    html_entity_decode(Configuration::get('PAYXPERT_PASSWORD'))
);

if ($c2pClient->handleCallbackStatus()) {
    $status = $c2pClient->getStatus();

    // get the Error code
    $errorCode = $status->getErrorCode();
    $errorMessage = $status->getErrorMessage();

    $transaction = $status->getLastTransactionAttempt();

    if ($transaction !== null) {
        $transactionId = $transaction->getTransactionID();

        $orderId = $status->getOrderID();
        $amount = number_format($status->getAmount() / 100, 2, '.', '');
        $callbackData = $status->getCtrlCustomData();

        $message = "PayXpert payment module: ";

        // load the customer cart and perform some checks
        $cart = new Cart((int) ($orderId));
        if (!$cart->id) {
            $message .= "Cart is empty: " . $orderId;
            error_log($message);
        }

        $responseStatus = "KO";
        $responseMessage = "Callback validation failed";
        $customer = new Customer((int) ($cart->id_customer));

        if (!$customer) {
            $message .= "Customer is empty for order " . $orderId;
            error_log($message);
        } else {
            if (!PayXpert::checkCallbackAuthenticityData($callbackData, $cart->id, $customer->secure_key)) {
                $message .= "Invalid callback received for order " . $orderId . ". Validation failed.";
                error_log($message);
            } else {
                $responseStatus = "OK";
                $responseMessage = "Status recorded";

                $message .= "Error code: " . $errorCode . "<br />";
                $message .= "Error message: " . $errorMessage . "<br />";
                $message .= "Transaction ID: " . $transactionId . "<br />";
                $message .= "Order ID: " . $orderId . "<br />";

                error_log(str_replace("<br />", " ", $message));

                $paymentMean = $payxpert->l('Credit Card') . ' (PayXpert)';

                switch ($errorCode) {
                    case "000":
                        /* Payment OK */
                        $payxpert->validateOrder((int) $orderId, _PS_OS_PAYMENT_, $amount, $paymentMean, $message);
                        break;
                    default:
                        $payxpert->validateOrder((int) $orderId, _PS_OS_ERROR_, $amount, $paymentMean, $message);
                        break;
                }
            }
        }
    }

    // Send a response to mark this transaction as notified
    $response = array("status" => $responseStatus, "message" => $responseMessage);
    header("Content-type: application/json");
    echo json_encode($response);
}
