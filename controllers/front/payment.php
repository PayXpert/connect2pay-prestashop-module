<?php

use Payxpert\Classes\PayxpertConfiguration;
use Payxpert\Classes\PayxpertPaymentMethod;
use PayXpert\Connect2Pay\containers\constant\PaymentMethod;
use PayXpert\Connect2Pay\containers\constant\PaymentMode;
use Payxpert\Exception\ConfigurationNotFoundException;
use Payxpert\Exception\PaymentMethodNotFoundException;
use Payxpert\Exception\PayxpertException;
use Payxpert\Utils\Logger;
use Payxpert\Utils\Webservice;

class PayxpertPaymentModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        try {
            Logger::info('Payment Call');
            $configuration = PayxpertConfiguration::getCurrentObject();

            if (!$configuration) {
                throw new ConfigurationNotFoundException();
            }

            $paymentMethodId = Tools::getValue('payxpert_payment_method');

            if (
                !$paymentMethodId
                || !is_numeric($paymentMethodId)
                || !$configuration->hasConfigurationPaymentMethodId($paymentMethodId)
            ) {
                throw new PaymentMethodNotFoundException();
            }

            $paymentMethod = new PayxpertPaymentMethod($paymentMethodId);
            $paymentMethodConfig = json_decode($paymentMethod->config, true);
            $paymentMode = $paymentMethodConfig['payment_mode'] ?? PaymentMode::SINGLE;

            $preparedPayment = Webservice::preparePayment(
                $configuration,
                $paymentMethodConfig['payment_method'] ?? PaymentMethod::CREDIT_CARD,
                $this->context->cart,
                $paymentMode,
                PaymentMode::INSTALMENTS == $paymentMode ? (
                    isset($paymentMethodConfig['instalment_configuration']) ? [
                        'firstPercentage' => $configuration->{$paymentMethodConfig['instalment_configuration']},
                        'xTimes' => $paymentMethodConfig['instalment_x_times'],
                    ] : []
                ) : []
            );

            if (isset($preparedPayment['error'])) {
                throw new Exception($preparedPayment['error']);
            }

            $urlRedirect = $preparedPayment['redirectUrl'];
        } catch (PayxpertException $ce) {
            $this->errors[] = $ce->getMessage();
        } catch (Exception $e) {
            Logger::critical($e->getMessage());
            $this->errors[] = $this->module->l('An error occurred during the payment process. Please try again.', 'payxpertpaymentmodule');
        }

        if (!empty($this->errors)) {
            $this->redirectWithNotifications($this->context->link->getPageLink('order', true, null, ['step' => 3]));
        }

        Tools::redirect(isset($urlRedirect) ? $urlRedirect : 'index.php');
    }
}
