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

/**
 *
 * @since 1.5.0
 */
class PayxpertValidationModuleFrontController extends ModuleFrontController
{

    public function __construct()
    {
        parent::__construct();
        $this->display_header = false;
        $this->display_footer = false;
    }

    /**
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        // $cart = $this->context->cart;
        require_once dirname(__FILE__) . '/../../lib/Connect2PayClient.php';

        // init api
        $c2pClient = new PayXpert\Connect2Pay\Connect2PayClient(
            $this->module->getPayXpertUrl(),
            Configuration::get('PAYXPERT_ORIGINATOR'),
            html_entity_decode(Configuration::get('PAYXPERT_PASSWORD'))
        );

        if ($c2pClient->handleCallbackStatus()) {
            $responseStatus = "KO";
            $responseMessage = "Callback validation failed";

            $status = $c2pClient->getStatus();

            // get the Error code
            $errorCode = $status->getErrorCode();
            $errorMessage = $status->getErrorMessage();

            $transaction = $status->getLastTransactionAttempt();

            if ($transaction !== null) {
                $transactionId = $transaction->getTransactionID();

                $orderId = $status->getOrderID();
                $amount = number_format($transaction->getAmount() / 100, 2, '.', '');
                $callbackData = $status->getCtrlCustomData();

                $severity = 1;

                $message = "PayXpert payment module: ";
                $message .= "Received a new transaction status callback from " . $_SERVER["REMOTE_ADDR"] . ". ";
                $message .= "Error code: " . $errorCode . " ";
                $message .= "Error message: " . $errorMessage . " ";
                $message .= "Transaction ID: " . $transactionId . " ";
                $message .= "Order ID: " . $orderId . " ";

                $customer = null;
                if (version_compare(_PS_VERSION_, '1.5', '>=')) {
                    $cart = new Cart((int) $orderId);
                    $customer = new Customer((int) ($cart->id_customer));
                }

                // For Prestashop >= 1.7, multi payment types is possible
                switch ($transaction->getPaymentMethod()) {
                    case PayXpert\Connect2Pay\Connect2PayClient::PAYMENT_METHOD_BANKTRANSFER:
                        $paymentMean = $this->module->l('Bank Transfer');
                        break;
                    default:
                        $paymentMean = $this->module->l('Credit Card');
                        break;
                }

                $paymentMean .= ' (PayXpert)';

                if (!$customer) {
                    $message .= "Customer not found for order " . $orderId;
                    error_log($message);
                    $severity = 3;
                } else {
                    if (!PayXpert::checkCallbackAuthenticityData($callbackData, $cart->id, $customer->secure_key)) {
                        $message .= "Invalid callback received for order " . $orderId . ". Validation failed.";
                        error_log($message);
                        $severity = 3;
                    } else {
                        switch ($errorCode) {
                            case "000":
                                // Payment OK
                                $this->module->validateOrder(
                                    (int) $orderId,
                                    Configuration::get('PS_OS_PAYMENT'),
                                    $amount,
                                    $paymentMean,
                                    $errorMessage,
                                    array('transaction_id' => $transactionId),
                                    null,
                                    false,
                                    $customer->secure_key
                                );
                                break;
                            default:
                                // Lets not validate, it lets customer retry the transaction
                                // $this->module->validateOrder(
                                //     (int) $orderId,
                                //     Configuration::get('PS_OS_ERROR'),
                                //     $amount,
                                //     $paymentMean,
                                //     $errorMessage,
                                //     array('transaction_id' => $transactionId),
                                //     null,
                                //     false,
                                //     $customer->secure_key
                                // );
                                $severity = 2;
                                break;
                        }
                        $responseStatus = "OK";
                        $responseMessage = "Payment status recorded";
                    }
                }

                if (version_compare(_PS_VERSION_, '1.7', '<') && version_compare(_PS_VERSION_, '1.6', '>=')) {
                    $this->module->saveTransaction(array(
                        'id_order' => (int)$this->module->currentOrder,
                        'transaction_id' => $transactionId,
                        'errorCode' => $errorCode
                    ));
                }
            }

            $this->module->addLog($message, $severity, $errorCode);

            // Send a response to mark this transaction as notified
            $response = array("status" => $responseStatus, "message" => $responseMessage);
            header("Content-type: application/json");
            echo json_encode($response);
            exit();
        } else {
            $this->module->addLog("PayXpert payment module:\n Callback received an incorrect status from " . $_SERVER["REMOTE_ADDR"], 2);
        }
    }
}
