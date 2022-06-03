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

use PayXpert\Connect2Pay\containers\constant\PaymentMethod;
use PayXpert\Connect2Pay\containers\constant\PaymentNetwork;

/**
 *
 * @since 1.5.0
 */
class PayxpertPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;

        // Default value for Prestashop < 1.7
        $template = 'payment_execution.tpl';

        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $template = 'module:' . $this->module->name . '/views/templates/front/payment_execution_credit_card.tpl';
        }

        $params = array();

        // These should be filled only with Prestashop >= 1.7
        $paymentType = Tools::getValue('payment_type', null);
        $paymentProvider = Tools::getValue('payment_provider', null);

        if ($paymentType !== null && $this->module->validatePaymentMethod($paymentType)) {
            $params['payment_type'] = $paymentType;

            if ($paymentProvider !== null && $this->module->validatePaymentNetwork($paymentProvider)) {
                $params['payment_provider'] = $paymentProvider;
            }

            switch ($paymentType) {
                case PaymentMethod::BANK_TRANSFER:
                    if (version_compare(_PS_VERSION_, '1.7', '>=')) {
                        $template = 'module:' . $this->module->name . '/views/templates/front/payment_execution_bank_transfer.tpl';
                    } else {
                        $template = 'payment_execution_bank_transfer16.tpl';
                    }

                    if (isset($params['payment_provider'])) {
                        switch ($params['payment_provider']) {
                            case PaymentNetwork::SOFORT:
                                $paymentLogo = 'sofort';
                                break;
                            case PaymentNetwork::PRZELEWY24:
                                $paymentLogo = 'przelewy24';
                                break;
                            case PaymentNetwork::IDEAL:
                                $paymentLogo = 'ideal';
                                break;
                            case PaymentNetwork::GIROPAY:
                                $paymentLogo = 'giropay';
                                break;
                        }
                    }
                    break;
                case PaymentMethod::WECHAT:
                    $paymentLogo = 'wechat';
                    $template = 'payment_execution_wechat16.tpl';
                    break;
                case PaymentMethod::ALIPAY:
                    $paymentLogo = 'alipay';
                    $template = 'payment_execution_alipay16.tpl';
                    break;
                default:
                    $paymentLogo = 'creditcard';
                    break;
            }

            $this->context->smarty->assign("payment_logo", $paymentLogo);
        }

        $this->context->smarty->assign(
            array(/* */
                'nbProducts' => $cart->nbProducts(), /* */
                'cust_currency' => (int)($cart->id_currency), /* */
                'total' => $cart->getOrderTotal(true, Cart::BOTH), /* */
                'isoCode' => $this->context->language->iso_code, /* */
                'this_path' => $this->module->getPathUri(), /* */
                'this_link' => $this->module->getModuleLinkCompat('payxpert', 'redirect', $params), /* */
                'this_link_back' => $this->module->getPageLinkCompat('order', true, null, "step=3") /* */
            ) /* */
        );

        $this->setTemplate($template);
    }
}
