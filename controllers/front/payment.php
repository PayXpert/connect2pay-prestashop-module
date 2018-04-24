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

require_once dirname(__FILE__) . '/../../lib/Connect2PayClient.php';

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

        $params = array();

        // These should be filled only with Prestashop >= 1.7
        $paymentType = Tools::getValue('payment_type', null);
        $paymentProvider = Tools::getValue('payment_provider', null);

        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            if ($paymentType !== null && PayXpert\Connect2Pay\C2PValidate::isPayment($paymentType)) {
                $params['payment_type'] = $paymentType;

                if ($paymentProvider !== null && PayXpert\Connect2Pay\C2PValidate::isProvider($paymentProvider)) {
                    $params['payment_provider'] = $paymentProvider;
                }

                switch ($paymentType) {
                    case PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_TYPE_BANKTRANSFER:
                        $template = 'module:' . $this->module->name . '/views/templates/front/payment_execution_bank_transfer.tpl';

                        if (isset($params['payment_provider'])) {
                            switch ($params['payment_provider']) {
                                case PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_PROVIDER_SOFORT:
                                    $paymentLogo = 'sofort';
                                    break;
                                case PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_PROVIDER_PRZELEWY24:
                                    $paymentLogo = 'przelewy24';
                                    break;
                                case PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_PROVIDER_IDEALKP:
                                    $paymentLogo = 'ideal';
                                    break;
                            }
                        }
                        break;
                    default:
                        // Default is Credit Card
                        $template = 'module:' . $this->module->name . '/views/templates/front/payment_execution_credit_card.tpl';
                        $paymentLogo = 'creditcard';
                        break;
                }

                $this->context->smarty->assign("payment_logo", $paymentLogo);
            }
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
