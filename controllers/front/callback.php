<?php

use Payxpert\Classes\PayxpertConfiguration;
use Payxpert\Classes\PayxpertPaymentToken;
use Payxpert\Classes\PayxpertPaymentTransaction;
use Payxpert\Classes\PayxpertSubscription;
use Payxpert\Exception\ConfigurationNotFoundException;
use Payxpert\Exception\HandleFailedException;
use Payxpert\Exception\HashFailedException;
use Payxpert\Exception\PaymentCancellationException;
use Payxpert\Exception\PaymentTokenNotFoundException;
use Payxpert\Utils\Logger;
use Payxpert\Utils\Utils;
use Payxpert\Utils\Webservice;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;

class PayxpertCallbackModuleFrontController extends ModuleFrontController
{
    /** @var PaymentModule */
    public $module;

    public function postProcess()
    {
        $responseStatus = 'OK';
        $responseMessage = 'Payment status recorded';

        try {
            Logger::info('Callback Call');

            $configuration = PayxpertConfiguration::getCurrentObject();
            if (!Validate::isLoadedObject($configuration)) {
                throw new ConfigurationNotFoundException();
            }

            $handle = Webservice::handleCallback($configuration);

            if (!$handle) {
                throw new HandleFailedException();
            }

            if ($handle['errorCode'] == PayxpertPaymentTransaction::RESULT_CODE_CALLBACK_CANCEL) {
                throw new PaymentCancellationException();
            }

            $paymentToken = PayxpertPaymentToken::getByMerchantToken($handle['transaction']->getPaymentMerchantToken());

            if (!Validate::isLoadedObject($paymentToken)) {
                throw new PaymentTokenNotFoundException();
            }

            $configuration = PayxpertConfiguration::getCurrentObject();

            if (!Validate::isLoadedObject($configuration)) {
                throw new ConfigurationNotFoundException();
            }

            $customer = new Customer($paymentToken->id_customer);
            $cart = new Cart($paymentToken->id_cart);
            $secureToken = Tools::getValue('secureToken');
            $customData = [];

            if (isset($handle['customData'])) {
                parse_str($handle['customData'], $customData);
            }

            // Check if the customer redirected is the one that process the paiement
            $crypto = ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\Crypto\\Hashing');
            if (!$crypto->checkHash($customer->id . $customer->secure_key, $secureToken)) {
                throw new HashFailedException();
            }

            // All good - Process to validateOrder
            $status = _PS_OS_PAYMENT_;

            if (PayxpertConfiguration::CAPTURE_MODE_MANUAL == $configuration->capture_mode) {
                $status = Configuration::get('OS_PAYXPERT_WAITING_CAPTURE');
            }

            if (isset($customData['status'])) {
                $status = $customData['status'];
            }

            if (PayxpertPaymentTransaction::RESULT_CODE_SUCCESS !== $handle['errorCode']) {
                $status = _PS_OS_ERROR_;
            }

            $order = Order::getByCartId($cart->id);
            if (!Validate::isLoadedObject($order)) {
                $paymentMethodName = $this->module->name . ' - ' . $handle['transaction']->getPaymentMethod();

                $this->module->validateOrder(
                    (int) $cart->id,
                    $status,
                    (float) ($handle['transaction']->getAmount() / 100),
                    $paymentMethodName,
                    null,
                    [
                        'transaction_id' => $handle['transaction']->getTransactionId(),
                    ],
                    (int) $cart->id_currency,
                    false,
                    $customer->secure_key,
                    null,
                    $handle['transaction']->getOrderID()
                );

                $order = Order::getByCartId($cart->id);
            }

            if (version_compare(_PS_VERSION_, '8.0.0', '<')) {
                $order->reference = $handle['orderId'];
                $order->save();
            }

            $paymentMeanInfo = $handle['transaction']->getPaymentMeanInfo();
            $payxpertPaymentTransaction = new PayxpertPaymentTransaction();
            $payxpertPaymentTransaction->id_shop = $this->context->cart->id_shop;
            $payxpertPaymentTransaction->transaction_id = $handle['transaction']->getTransactionId();
            $payxpertPaymentTransaction->transaction_referal_id = $handle['transaction']->getRefTransactionID();
            $payxpertPaymentTransaction->order_id = $order->id;
            $payxpertPaymentTransaction->payment_id = $handle['transaction']->getPaymentID();
            $payxpertPaymentTransaction->liability_shift = method_exists($paymentMeanInfo, 'getIs3DSecure') ? $paymentMeanInfo->getIs3DSecure() : false;
            $payxpertPaymentTransaction->payment_method = $handle['transaction']->getPaymentMethod();
            $payxpertPaymentTransaction->operation = $handle['transaction']->getOperation();
            $payxpertPaymentTransaction->amount = number_format($handle['transaction']->getAmount() / 100, 2, '.', '');
            $payxpertPaymentTransaction->currency = $handle['transaction']->getCurrency();
            $payxpertPaymentTransaction->result_code = $handle['transaction']->getResultCode();
            $payxpertPaymentTransaction->result_message = $handle['transaction']->getResultMessage();
            $payxpertPaymentTransaction->subscription_id = $handle['transaction']->getSubscriptionID();
            $payxpertPaymentTransaction->save();

            if ($payxpertPaymentTransaction->subscription_id != 0) {
                $subscriptionInfo = Webservice::getStatusSubscription($configuration, $payxpertPaymentTransaction->subscription_id);
                if (isset($subscriptionInfo['error'])) {
                    Logger::critical('Error while retrieving subscriptionInfo for paymentTransactionID : ' . $payxpertPaymentTransaction->id);
                } else {
                    $payxpertSubscription = new PayxpertSubscription();
                    $subscriptionInfo = Utils::toSnakeCaseKeys($subscriptionInfo['subscription']);
                    $payxpertSubscription->hydrate($subscriptionInfo);
                    $payxpertSubscription->save();
                }
            }
        } catch (\Exception $e) {
            Logger::critical($e->getMessage());
            $responseStatus = 'KO';
            $responseMessage = 'Callback validation failed';
        } finally {
            header('Content-type: application/json');
            echo json_encode([
                'status' => $responseStatus,
                'message' => $responseMessage,
            ]);
        }
    }
}
