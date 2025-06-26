<?php

use Payxpert\Classes\PayxpertConfiguration;
use Payxpert\Classes\PayxpertPaymentToken;
use Payxpert\Classes\PayxpertPaymentTransaction;
use Payxpert\Utils\Logger;
use Payxpert\Utils\Webservice;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;

class PayxpertRedirectModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $urlRedirect = 'index.php?controller=order';

        try {
            Logger::info('Redirect Call');
            $data = Tools::getValue('data');
            $customerToken = Tools::getValue('customer');
            $secureToken = Tools::getValue('secureToken');

            if (!$data || !$customerToken) {
                throw new Exception('Missing : data || customerToken || secureToken');
            }

            $configuration = PayxpertConfiguration::getCurrentObject();

            if (!$configuration) {
                throw new Exception('No configuration');
            }

            $paymentToken = PayxpertPaymentToken::getByCustomerToken($customerToken);

            if (!Validate::isLoadedObject($paymentToken)) {
                throw new Exception('No paymentToken found for customerToken : ' . substr($customerToken, 0, 50));
            }

            $tokenExpired = ((new DateTime())->getTimestamp() - (new DateTime($paymentToken->date_add))->getTimestamp()) > 600;
            $customer = new Customer($paymentToken->id_customer);
            $result = Webservice::handleRedirect($configuration, $paymentToken->merchant_token, $data);

            // Check if the customer redirected is the one that process the paiement
            $crypto = ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\Crypto\\Hashing');
            if (!$crypto->checkHash($customer->id . $customer->secure_key, $secureToken)) {
                throw new Exception('CheckHash failed !');
            }

            // Rebuild customer information
            if (!$tokenExpired) {
                $this->reconnectCustomer($paymentToken);
            } else {
                Logger::info('Token expired for customID : ' . $customer->id);
            }

            $cart = new Cart($paymentToken->id_cart);
            $order = Order::getByCartId($cart->id);

            switch ($result['errorCode']) {
                case PayxpertPaymentTransaction::RESULT_CODE_SUCCESS:
                    $urlRedirect = $this->context->link->getPageLink(
                        'order-confirmation',
                        true,
                        (int) $customer->id_lang,
                        [
                            'id_cart' => (int) $cart->id,
                            'id_module' => (int) $this->module->id,
                            'id_order' => (int) $order->id,
                            'key' => $customer->secure_key,
                        ],
                        false,
                        $order->id_shop
                    );
                    break;
                case PayxpertPaymentTransaction::RESULT_CODE_CANCEL:
                    $urlRedirect = $this->context->link->getPageLink(
                        'order',
                        true,
                        (int) $customer->id_lang
                    );
                    break;
                default:
                    $urlRedirect = $this->context->link->getPageLink(
                        'order-detail',
                        true,
                        (int) $customer->id_lang,
                        [
                            'id_order' => (int) $order->id,
                        ],
                        false,
                        $order->id_shop
                    );
            }
        } catch (Exception $e) {
            Logger::critical($e->getMessage());
            $urlRedirect = 'index.php';
        }

        Tools::redirect($urlRedirect);
    }

    private function reconnectCustomer($paymentToken)
    {
        if (!$this->context->customer->isLogged()) {
            Logger::info('Rebuild customer information');
            $customer = new Customer($paymentToken->id_customer);
            $this->context->customer = $customer;
            $this->context->customer->logged = true;
            $this->context->cookie->__set('id_customer', $customer->id);
            $this->context->cookie->__set('customer_lastname', $customer->lastname);
            $this->context->cookie->__set('customer_firstname', $customer->firstname);
            $this->context->cookie->__set('logged', 1);
            $this->context->cookie->__set('passwd', $customer->passwd);
            $this->context->cookie->__set('email', $customer->email);
            $this->context->cookie->__set('id_cart', $paymentToken->id_cart);
            if (version_compare(_PS_VERSION_, '1.7.6.6', '>=')) {
                $this->context->cookie->registerSession(new CustomerSession());
            }
            $this->context->cookie->write();
        }
    }
}
