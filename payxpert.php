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

if (!defined('_PS_VERSION_')) {
    exit();
}

require_once(dirname(__FILE__) . '/vendor/autoload.php');

use PayXpert\Connect2Pay\Connect2PayClient;
use PayXpert\Connect2Pay\containers\request\PaymentPrepareRequest;
use PayXpert\Connect2Pay\containers\Order;
use PayXpert\Connect2Pay\containers\Shipping;
use PayXpert\Connect2Pay\containers\Shopper;
use PayXpert\Connect2Pay\containers\Account;
use PayXpert\Connect2Pay\containers\constant\OrderShippingType;
use PayXpert\Connect2Pay\containers\constant\OrderType;
use PayXpert\Connect2Pay\containers\constant\PaymentMethod;
use PayXpert\Connect2Pay\containers\constant\PaymentMode;
use PayXpert\Connect2Pay\containers\constant\AccountAge;
use PayXpert\Connect2Pay\containers\constant\AccountLastChange;
use PayXpert\Connect2Pay\containers\constant\AccountPaymentMeanAge;

class PayXpert extends PaymentModule
{
    protected $_postErrors = array();
    protected $_html = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'payxpert';
        $this->version = '1.3.0';
        $this->module_key = '36f0012c50e666c56801493e0ad709eb';

        $this->tab = 'payments_gateways';

        $this->author = 'PayXpert';
        $this->need_instance = 1;

        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 1;

        if (version_compare(_PS_VERSION_, '1.5', '>=')) {
            $this->ps_versions_compliancy = array('min' => '1.4.0.0', 'max' => _PS_VERSION_);
        }

        if (version_compare(_PS_VERSION_, '1.6', '>=')) {
            $this->bootstrap = true;
        }

        parent::__construct();

        $this->displayName = 'PayXpert Payment Solutions';
        $this->description = $this->l("Accept payments today with PayXpert");
        $this->confirmUninstall = $this->l('Are you sure about removing these details?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

        /* For 1.4.3 and less compatibility */
        $updateConfig = array('PS_OS_CHEQUE' => 1, 'PS_OS_PAYMENT' => 2, 'PS_OS_PREPARATION' => 3, 'PS_OS_SHIPPING' => 4,
            'PS_OS_DELIVERED' => 5, 'PS_OS_CANCELED' => 6, 'PS_OS_REFUND' => 7, 'PS_OS_ERROR' => 8, 'PS_OS_OUTOFSTOCK' => 9,
            'PS_OS_BANKWIRE' => 10, 'PS_OS_PAYPAL' => 11, 'PS_OS_WS_PAYMENT' => 12);

        foreach ($updateConfig as $u => $v) {
            if (!Configuration::get($u) || (int) Configuration::get($u) < 1) {
                if (defined('_' . $u . '_') && (int) constant('_' . $u . '_') > 0) {
                    Configuration::updateValue($u, constant('_' . $u . '_'));
                } else {
                    Configuration::updateValue($u, $v);
                }
            }
        }
    }

    /**
     * Install method
     */
    public function install()
    {
        // call parents
        if (!parent::install()) {
            $errorMessage = Tools::displayError($this->l('PayXpert installation : install failed.'));
            $this->addLog($errorMessage, 3, '000002');
            return false;
        }

        $hookResult = true;

        if (version_compare(_PS_VERSION_, '1.7', '<')) {
            if (version_compare(_PS_VERSION_, '1.6', '>=')) {
                if (!$this->installDB()) {
                    $errorMessage = Tools::displayError($this->l('PayXpert installation : Tables failed.'));
                    $this->addLog($errorMessage, 3, '000002');

                    return false;
                }
                $hookResult = $hookResult && $this->registerHook('adminOrder') && $this->registerHook('header');
            }
            $hookResult = $hookResult && $this->registerHook('payment');
        } else {
            $hookResult = $hookResult && $this->registerHook('paymentOptions');
        }
        $hookResult = $hookResult && $this->registerHook('paymentReturn');

        if (!$hookResult) {
            $errorMessage = Tools::displayError($this->l('PayXpert installation : hooks failed.'));
            $this->addLog($errorMessage, 3, '000002');

            return false;
        }

        // Add configuration parameters
        foreach ($this->getModuleParameters() as $parameter) {
            if (!Configuration::updateValue($parameter, '')) {
                $errorMessage = Tools::displayError($this->l('PayXpert installation : configuration failed.'));
                $this->addLog($errorMessage, 3, '000002');

                return false;
            }
        }

        $this->addLog($this->l('PayXpert installation : installation successful'));

        return true;
    }

    public function installDB()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . $this->name . "_transactions` (
                `id_". $this->name . "_transaction` int(11) NOT NULL AUTO_INCREMENT,
                `id_order` int(11) NOT NULL,
                `transaction_id` varchar(32) NOT NULL,
                `error_code` int(11) NOT NULL,
                `refund` decimal(20,6) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_". $this->name . "_transaction`)
              ) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8";
        if (!Db::getInstance()->execute($sql)) {
            return false;
        }
        return true;
    }

    /**
     * Uninstall the module
     *
     * @return boolean
     */
    public function uninstall()
    {
        $result = parent::uninstall();

        foreach ($this->getModuleParameters() as $parameter) {
            $result = $result || Configuration::deleteByName($parameter);
        }

        return $result;
    }

    private function getModuleParameters()
    {
        $moduleParameters = array( /* */
            'PAYXPERT_ORIGINATOR', /* */
            'PAYXPERT_PASSWORD', /* */
            'PAYXPERT_URL', /* */
            'PAYXPERT_MERCHANT_NOTIF', /* */
            'PAYXPERT_MERCHANT_NOTIF_TO', /* */
            'PAYXPERT_MERCHANT_NOTIF_LANG' /* */
        );

        if (version_compare(_PS_VERSION_, '1.6', '>=')) {
            $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_CREDIT_CARD';
            $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_SOFORT';
            $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_PRZELEWY24';
            $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_IDEAL';
            $moduleParameters[] = 'PAYXPERT_IS_IFRAME';
            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_GIROPAY';
                $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_WECHAT';
                $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_ALIPAY';
            }
        }

        return $moduleParameters;
    }

    public function saveTransaction($data)
    {
        $sql = "INSERT INTO `" . _DB_PREFIX_ . $this->name . "_transactions` (`id_order`, `transaction_id`, `error_code`) VALUES (".(int)$data['id_order'].", '".pSQL($data['transaction_id'])."', '".pSQL($data['errorCode'])."')";
        Db::getInstance()->execute($sql);
    }

    public function checkPaymentOption($params)
    {
        // if module disabled, can't go through
        if (!$this->active) {
            return false;
        }

        // Check if currency ok
        if (!$this->checkCurrency($params['cart'])) {
            return false;
        }

        // Check if module is configured
        if (Configuration::get('PAYXPERT_ORIGINATOR') == "" && Configuration::get('PAYXPERT_PASSWORD') == "") {
            return false;
        }

        return true;
    }

    public function hookHeader()
    {
        if (
            $this->context->controller instanceof OrderController ||
            $this->context->controller instanceof OrderOpcController
        ) {
            $this->context->controller->addJqueryPlugin('fancybox');
            $this->context->controller->addJs($this->_path.'/views/js/seamless.js');
        }
    }

    public function hookAdminOrder($params)
    {
        $order = new Order($params['id_order']);

        if ($order->module != $this->name) {
            return;
        }
        $currency = Currency::getCurrencyInstance($order->id_currency);
        $currencySymbol = $currency->getSign();

        $ajaxLink = $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&tab_module=' .
            $this->tab . '&action=doRefund&module_name=' . $this->name;

        list($orderTotal, $totalRefunded, $refundAvailable) = $this->getRefund($order);

        $this->context->smarty->assign(array(
            'pxpRefundLink' => $ajaxLink,
            'pxpOrder' => $order,
            'pxpOrderTotal' => $orderTotal,
            'pxpTotalRefund' => $totalRefunded,
            'pxpRefundAvailable' => $refundAvailable,
            'pxpOrderCurrency' => $currency,
            'pxpCurrencySymbol' => $currencySymbol
        ));

        return $this->display(__FILE__, '/views/templates/admin/admin_order.tpl');
    }

    public function getRefund(Order $order)
    {
        if (!Validate::isLoadedObject($order)) {
            return false;
        }

        $orderTotal = $order->getTotalPaid();
        $totalRefunded = $this->getTransactionData((int)$order->id, 'refund');
        $refundAvailable = $orderTotal - $totalRefunded;

        return array($orderTotal, $totalRefunded, $refundAvailable);
    }

    public function getTransactionData($id_order, $field = 'transaction_id')
    {
        return Db::getInstance()->getValue(
            "SELECT ".pSQL($field)." FROM `" . _DB_PREFIX_ . $this->name . "_transactions` WHERE id_order = ".(int)$id_order
        );
    }

    public function ajaxProcessDoRefund()
    {
        $pxAmount = str_replace(',', '.', Tools::getValue('pxpRefundAmount'));

        $id_order = (int)Tools::getValue('pxpOrder');
        $transactionID = $this->getTransactionData($id_order);
        if (!$transactionID) {
            die(Tools::jsonEncode(array(
                'success' => false,
                'msg' => $this->l('Transaction Id not found')
            )));
        }

        $order = new Order($id_order);

        list($orderTotal, $totalRefunded, $refundAvailable) = $this->getRefund($order);

        if ($refundAvailable >= $pxAmount && $this->refundTransaction($transactionID, $pxAmount)) {
            $order = new Order($id_order);
            if ($order->getCurrentState() != Configuration::get('PS_OS_REFUND')) {
                $order->setCurrentState(Configuration::get('PS_OS_REFUND'));
            }
            die(Tools::jsonEncode(array(
                'success' => true,
                'msg' => $this->l('Refund successful')
            )));
        } else {
            die(Tools::jsonEncode(array(
                'success' => false,
                'msg' => $this->l('Refund could not be processed')
            )));
        }
    }

    public function refundTransaction($transactionID, $pxAmount)
    {
        $c2pClient = new Connect2PayClient(
            $this->getPayXpertUrl(),
            Configuration::get('PAYXPERT_ORIGINATOR'),
            html_entity_decode(Configuration::get('PAYXPERT_PASSWORD'))
        );

        $pxAmount = (float)($pxAmount * 1000); // we use this trick to avoid rounding while converting to int
        $pxAmount = (float)($pxAmount / 10); // unless sometimes 17.90 become 17.89
        $pxAmount = (int)$pxAmount;

        $response = $c2pClient->refundTransaction($transactionID, (int)($pxAmount));

        if ($response && $response->getCode() == '000') {
            return Db::getInstance()->execute(
                "UPDATE `" . _DB_PREFIX_ . $this->name . "_transactions` SET refund = refund+" . (float)($pxAmount / 100) . ' WHERE transaction_id = '.pSQL($transactionID)
            );
        }

        return false;
    }

    /**
     * Hook payment options
     *
     * @since Prestashop 1.7
     * @param type $params
     * @return type
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->checkPaymentOption($params)) {
            return;
        }

        $controller = 'payment';

        if ($this->isIframeMode()) {
            $controller = 'iframe';
        }

        $this->smarty->assign($this->getTemplateVarInfos());

        $payment_options = array();

        $ccOption = $this->getCreditCardPaymentOption($controller);
        if ($ccOption != null) {
            $payment_options[] = $ccOption;
        }
        $sofortOption = $this->getBankTransferViaSofortPaymentOption($controller);
        if ($sofortOption != null) {
            $payment_options[] = $sofortOption;
        }
        $przelewy24Option = $this->getBankTransferViaPrzelewy24PaymentOption($controller);
        if ($przelewy24Option != null) {
            $payment_options[] = $przelewy24Option;
        }
        $idealOption = $this->getBankTransferViaIDealPaymentOption($controller);
        if ($idealOption != null) {
            $payment_options[] = $idealOption;
        }

        return $payment_options;
    }

    /**
     *
     * @since Prestashop 1.7
     */
    public function getCreditCardPaymentOption($controller)
    {
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_CREDIT_CARD') == "true") {
            $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $option->setModuleName($this->name);
            $option->setCallToActionText($this->l('Pay by Credit Card'));
            $option->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    $controller,
                    array('payment_type' => PaymentMethod::CREDIT_CARD),
                    true
                )
            );

            $this->context->smarty->assign(
                'pxpCCLogo',
                Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment-types/creditcard.png')
            );

            $option->setAdditionalInformation($this->context->smarty->fetch('module:payxpert/views/templates/front/payment_infos_credit_card.tpl'));

            return $option;
        }

        return null;
    }

    /**
     *
     * @since Prestashop 1.7
     */
    public function getBankTransferViaSofortPaymentOption($controller)
    {
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_SOFORT') == "true") {
            $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $option->setModuleName($this->name);
            $option->setCallToActionText($this->l('Pay by Bank Transfer via Sofort'));
            $option->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    $controller,
                    array(
                        'payment_type' => PaymentMethod::BANK_TRANSFER,
                        'payment_provider' => PaymentNetwork::SOFORT
                    ),
                    true
                )
            );

            $this->context->smarty->assign(
                "pxpSofortLogo",
                Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment-types/sofort.png')
            );

            $option->setAdditionalInformation($this->context->smarty->fetch('module:payxpert/views/templates/front/payment_infos_bank_transfer_sofort.tpl'));

            return $option;
        }

        return null;
    }

    /**
     *
     * @since Prestashop 1.7
     */
    public function getBankTransferViaPrzelewy24PaymentOption($controller)
    {
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_PRZELEWY24') == "true") {
            $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $option->setModuleName($this->name);
            $option->setCallToActionText($this->l('Pay by Bank Transfer via Przelewy24'));
            $option->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    $controller,
                    array(
                        'payment_type' => PaymentMethod::BANK_TRANSFER,
                        'payment_provider' => PaymentNetwork::PRZELEWY24
                    ),
                    true
                )
            );

            $this->context->smarty->assign(
                "pxpPrzelewy24Logo",
                Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment-types/przelewy24.png')
            );

            $option->setAdditionalInformation($this->context->smarty->fetch('module:payxpert/views/templates/front/payment_infos_bank_transfer_przelewy24.tpl'));

            return $option;
        }

        return null;
    }

    /**
     *
     * @since Prestashop 1.7
     */
    public function getBankTransferViaIDealPaymentOption($controller)
    {
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_IDEAL') == "true") {
            $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $option->setModuleName($this->name);
            $option->setCallToActionText($this->l('Pay by Bank Transfer via iDeal'));
            $option->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    $controller,
                    array(
                        'payment_type' => PaymentMethod::BANK_TRANSFER,
                        'payment_provider' => PaymentNetwork::IDEAL
                    ),
                    true
                )
            );

            $this->context->smarty->assign(
                "pxpIdealLogo",
                Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment-types/ideal.png')
            );

            $option->setAdditionalInformation($this->context->smarty->fetch('module:payxpert/views/templates/front/payment_infos_bank_transfer_ideal.tpl'));

            return $option;
        }

        return null;
    }

    public function getTemplateVarInfos()
    {
        $cart = $this->context->cart;

        return array(/* */
            'nbProducts' => $cart->nbProducts(), /* */
            'cust_currency' => $cart->id_currency, /* */
            'total' => $cart->getOrderTotal(true, Cart::BOTH), /* */
            'isoCode' => $this->context->language->iso_code /* */
        );
    }

    /**
     * Hook payment for Prestashop < 1.7
     *
     * @param type $params
     * @return type
     */
    public function hookPayment($params)
    {
        if (!$this->checkPaymentOption($params)) {
            return;
        }

        $this->assignSmartyVariable(
            'this_path',
            $this->_path
        );

        $this->assignSmartyVariable(
            'this_path_ssl',
            (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8') .
                 __PS_BASE_URI__ . 'modules/payxpert/'
        );

        if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
            return $this->getPaymentOptions16();
        }

        $this->assignSmartyVariable(
            'this_link',
            $this->getModuleLinkCompat(
                'payxpert',
                'payment'
            )
        );

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    private function getPaymentOptions16($value='')
    {
        $controller = 'payment';
        $payment_options = "";

        if ($this->isIframeMode()) {
            $ccOption = $this->getCreditCardPaymentOption16('iframe');
        } else {
            $ccOption = $this->getCreditCardPaymentOption16($controller);
        }

        if ($ccOption != null) {
            $payment_options .= $ccOption;
        }


        $sofortOption = $this->getBankTransferViaSofortPaymentOption16($controller);
        if ($sofortOption != null) {
            $payment_options .= $sofortOption;
        }
        $przelewy24Option = $this->getBankTransferViaPrzelewy24PaymentOption16($controller);
        if ($przelewy24Option != null) {
            $payment_options .= $przelewy24Option;
        }
        $idealOption = $this->getBankTransferViaIDealPaymentOption16($controller);
        if ($idealOption != null) {
            $payment_options .= $idealOption;
        }
        $giropayOption = $this->getBankTransferViaGiroPayPaymentOption16($controller);
        if ($giropayOption != null) {
            $payment_options .= $giropayOption;
        }
        $weChatOption = $this->getBankTransferViaWeChatPaymentOption16($controller);
        if ($weChatOption != null) {
            $payment_options .= $weChatOption;
        }
        $alipayOption = $this->getBankTransferViaAliPayPaymentOption16($controller);
        if ($alipayOption != null) {
            $payment_options .= $alipayOption;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/payxpert.css');

        return $payment_options;
    }

    /**
     *
     * @since Prestashop 1.6
     */
    public function getCreditCardPaymentOption16($controller)
    {
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_CREDIT_CARD') == "true") {
            $this->context->smarty->assign(
                array(
                    'payxpertLink' => $this->context->link->getModuleLink(
                        $this->name,
                        $controller,
                        array(
                            'payment_type' => PaymentMethod::CREDIT_CARD,
                            'content_only' => (bool)($controller=='iframe')
                        ),
                        true
                    ),
                    'payxpertClass' => 'creditcard '.$controller,
                    'payxpertText' => $this->l('Pay by Credit Card')
                )
            );

            return $this->display(__FILE__, 'views/templates/hook/payment16.tpl');
        }

        return null;
    }

    /**
     *
     * @since Prestashop 1.6
     */
    public function getBankTransferViaSofortPaymentOption16($controller)
    {
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_SOFORT') == "true") {
            $this->context->smarty->assign(
                array(
                    'payxpertLink' => $this->context->link->getModuleLink(
                        $this->name,
                        $controller,
                        array(
                            'payment_type' => PaymentMethod::BANK_TRANSFER,
                            'payment_provider' => PaymentNetwork::SOFORT
                        ),
                        true
                    ),
                    'payxpertClass' => 'sofort type-'.$controller,
                    'payxpertText' => $this->l('Pay by Bank Transfer via Sofort')
                )
            );

            return $this->display(__FILE__, 'views/templates/hook/payment16.tpl');
        }

        return null;
    }

    /**
     *
     * @since Prestashop 1.6
     */
    public function getBankTransferViaPrzelewy24PaymentOption16($controller)
    {
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_PRZELEWY24') == "true") {
            $this->context->smarty->assign(
                array(
                    'payxpertLink' => $this->context->link->getModuleLink(
                        $this->name,
                        $controller,
                        array(
                            'payment_type' => PaymentMethod::BANK_TRANSFER,
                            'payment_provider' => PaymentNetwork::PRZELEWY24
                        ),
                        true
                    ),
                    'payxpertClass' => 'przelewy24 type-'.$controller,
                    'payxpertText' => $this->l('Pay by Bank Transfer via Przelewy24')
                )
            );

            return $this->display(__FILE__, 'views/templates/hook/payment16.tpl');
        }

        return null;
    }

    /**
     *
     * @since Prestashop 1.6
     */
    public function getBankTransferViaIDealPaymentOption16($controller)
    {
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_IDEAL') == "true") {
            $this->context->smarty->assign(
                array(
                    'payxpertLink' => $this->context->link->getModuleLink(
                        $this->name,
                        $controller,
                        array(
                            'payment_type' => PaymentMethod::BANK_TRANSFER,
                            'payment_provider' => PaymentNetwork::IDEAL
                        ),
                        true
                    ),
                    'payxpertClass' => 'ideal type-'.$controller,
                    'payxpertText' => $this->l('Pay by Bank Transfer via iDeal')
                )
            );

            return $this->display(__FILE__, 'views/templates/hook/payment16.tpl');
        }

        return null;
    }

    /**
     *
     * @since Prestashop 1.6
     */
    public function getBankTransferViaGiroPayPaymentOption16($controller)
    {
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_GIROPAY') == "true") {
            $this->context->smarty->assign(
                array(
                    'payxpertLink' => $this->context->link->getModuleLink(
                        $this->name,
                        $controller,
                        array(
                            'payment_type' => PaymentMethod::BANK_TRANSFER,
                            'payment_provider' => PaymentNetwork::GIROPAY
                        ),
                        true
                    ),
                    'payxpertClass' => 'giropay type-'.$controller,
                    'payxpertText' => $this->l('Pay by Bank Transfer via Giropay')
                )
            );

            return $this->display(__FILE__, 'views/templates/hook/payment16.tpl');
        }

        return null;
    }

    /**
     *
     * @since Prestashop 1.6
     */
    public function getBankTransferViaWeChatPaymentOption16($controller)
    {
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_WECHAT') == "true") {
            $this->context->smarty->assign(
                array(
                    'payxpertLink' => $this->context->link->getModuleLink(
                        $this->name,
                        $controller,
                        array(
                            'payment_type' => PaymentMethod::WECHAT
                        ),
                        true
                    ),
                    'payxpertClass' => 'wechat type-'.$controller,
                    'payxpertText' => $this->l('Pay by WeChat')
                )
            );

            return $this->display(__FILE__, 'views/templates/hook/payment16.tpl');
        }

        return null;
    }

    /**
     *
     * @since Prestashop 1.6
     */
    public function getBankTransferViaAliPayPaymentOption16($controller)
    {
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_ALIPAY') == "true") {
            $this->context->smarty->assign(
                array(
                    'payxpertLink' => $this->context->link->getModuleLink(
                        $this->name,
                        $controller,
                        array(
                            'payment_type' => PaymentMethod::ALIPAY
                        ),
                        true
                    ),
                    'payxpertClass' => 'alipay type-'.$controller,
                    'payxpertText' => $this->l('Pay by Bank Transfer via AliPay')
                )
            );

            return $this->display(__FILE__, 'views/templates/hook/payment16.tpl');
        }

        return null;
    }

    /**
     * Hook paymentReturn
     *
     * Displays order confirmation
     *
     * @param type $params
     * @return type
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        if (isset($params['objOrder'])) {
            // For Prestashop < 1.7
            $order = $params['objOrder'];
        } else {
            $order = $params['order'];
        }

        switch ($order->getCurrentState()) {
            case _PS_OS_PAYMENT_:
            // Ok
            case _PS_OS_OUTOFSTOCK_:
                $this->assignSmartyVariable('status', 'ok');
                break;

            case _PS_OS_BANKWIRE_:
                $this->assignSmartyVariable('status', 'pending');
                break;

            case _PS_OS_ERROR_:
            // Error
            default:
                $this->assignSmartyVariable('status', 'failed');
                break;
        }

        $this->assignSmartyVariable('this_link_contact', $this->getPageLinkCompat('contact', true));

        return $this->display(__FILE__, 'views/templates/hook/orderconfirmation.tpl');
    }

    /**
     * Init the payment
     *
     * In this method, we'll start to initialize the transaction
     * And redirect the customer
     *
     * For Prestashop >= 1.5
     *
     * @global type $cookie
     * @param Cart $cart
     * @return type
     */
    public function redirect($cart, $paymentType = null, $paymentNetwork = null)
    {
        // if module disabled, can't go through
        if (!$this->active) {
            return "Module is not active";
        }

        // Check if currency ok
        if (!$this->checkCurrency($cart)) {
            return "Incorrect currency";
        }

        // Check if module is configured
        if (Configuration::get('PAYXPERT_ORIGINATOR') == "" && Configuration::get('PAYXPERT_PASSWORD') == "") {
            return "Module is not setup";
        }

        if ($paymentType == null || !$this->validatePaymentMethod($paymentType)) {
            $paymentType = PaymentMethod::CREDITCARD;
        }

        if (!$this->checkPaymentTypeAndProvider($paymentType, $paymentNetwork)) {
            return "Payment type or provider is not enabled";
        }

        $payment = $this->getPaymentClient($cart, $paymentType, $paymentNetwork);

        // prepare API
        if ($payment->preparePayment() == false) {
            $message = "PayXpert : can't prepare transaction - " . $payment->getClientErrorMessage();
            $this->addLog($message, 3);
            return $message;
        }

        $this->context->cookie->__set("pxpToken", $payment->getMerchantToken());

        Tools::redirect($payment->getCustomerRedirectURL());
        exit();
    }

    public function validatePaymentMethod($paymentMethod) {
        return ((string) $paymentMethod == PaymentMethod::CREDIT_CARD ||
            (string) $paymentMethod == PaymentMethod::BANK_TRANSFER ||
            (string) $paymentMethod == PaymentMethod::WECHAT ||
            (string) $paymentMethod == PaymentMethod::ALIPAY);            
    }

    public function validatePaymentNetwork($paymentNetwork) {
        return ((string) $paymentNetwork == PaymentNetwork::SOFORT ||
            (string) $paymentNetwork == PaymentNetwork::PRZELEWY24 ||
            (string) $paymentNetwork == PaymentNetwork::IDEAL ||
            (string) $paymentNetwork == PaymentNetwork::GIROPAY ||
            (string) $paymentNetwork == PaymentNetwork::EPS ||
            (string) $paymentNetwork == PaymentNetwork::POLI ||
            (string) $paymentNetwork == PaymentNetwork::DRAGONPAY ||
            (string) $paymentNetwork == PaymentNetwork::TRUSTLY);
    }



    /**
     * Generates the Connect2Pay payment URL
     *
     * For Prestashop >= 1.5
     *
     * @global type $cookie
     * @param Cart $cart
     * @return type
     */
    public function getPaymentClient($cart, $paymentType = null, $paymentNetwork = null)
    {
        // get all informations
        $customer = new Customer((int) ($cart->id_customer));
        $currency = new Currency((int) ($cart->id_currency));
        $carrier = new Carrier((int) ($cart->id_carrier));
        $addr_delivery = new Address((int) ($cart->id_address_delivery));
        $addr_invoice = new Address((int) ($cart->id_address_invoice));

        $invoice_state = new State((int) ($addr_invoice->id_state));
        $invoice_country = new Country((int) ($addr_invoice->id_country));

        $delivery_state = new State((int) ($addr_delivery->id_state));
        $delivery_country = new Country((int) ($addr_delivery->id_country));

        $invoice_phone = (!empty($addr_invoice->phone)) ? $addr_invoice->phone : $addr_invoice->phone_mobile;
        $delivery_phone = (!empty($addr_delivery->phone)) ? $addr_delivery->phone : $addr_delivery->phone_mobile;

        // init api
        $c2pClient = new Connect2PayClient(
            $this->getPayXpertUrl(),
            Configuration::get('PAYXPERT_ORIGINATOR'),
            html_entity_decode(Configuration::get('PAYXPERT_PASSWORD'))
        );

        $prepareRequest = new PaymentPrepareRequest();
        $shopper = new Shopper();
        $account = new Account();
        $order = new Order();

        // customer informations
        $account->setAge(AccountAge::LESS_30_DAYS);
        $account->setDate("");
        $account->setLastChange(AccountLastChange::LESS_30_DAYS);
        $account->setLastChangeDate("");
        $account->setPaymentMeanAge(AccountPaymentMeanAge::LESS_30_DAYS);
        $account->setPaymentMeanDate("");
        $account->setSuspicious(false);

        $shopper->setAccount($account);
        $shopper->setId($cart->id_customer);
        $shopper->setEmail($customer->email);
        $shopper->setFirstName(Tools::substr($customer->firstname, 0, 35));
        $shopper->setLastName(Tools::substr($customer->lastname, 0, 35));
        $shopper->setAddress1(Tools::substr(trim($addr_invoice->address1), 0, 255));
        $shopper->setAddress2(Tools::substr(trim($addr_invoice->address2), 0, 255));
        $shopper->setZipcode(Tools::substr($addr_invoice->postcode, 0, 10));
        $shopper->setCity(Tools::substr($addr_invoice->city, 0, 50));
        $shopper->setState(Tools::substr($invoice_state->name, 0, 30));
        $shopper->setCountryCode($invoice_country->iso_code);
        $shopper->setHomePhonePrefix("")->setHomePhone(Tools::substr(trim($invoice_phone), 0, 20));


        $order->setShippingType(OrderShippingType::DIGITAL_GOODS);

        // Order informations
        $order->setId(Tools::substr(pSQL($cart->id), 0, 100));
        $order->setDescription(Tools::substr($this->l('Invoice:') . pSQL($cart->id), 0, 255));

        $shopper->setAccount($account);

        $total = number_format($cart->getOrderTotal(true, 3) * 100, 0, '.', '');
        
        // Set all information for the payment
        $prepareRequest->setAmount($total);
        $prepareRequest->setPaymentMethod($paymentType);
        $prepareRequest->setPaymentMode(PaymentMode::SINGLE);
        $prepareRequest->setCurrency($currency->iso_code);

        $prepareRequest->setShopper($shopper);
        $prepareRequest->setOrder($order);

        if ($paymentNetwork != null && $this->validatePaymentNetwork($paymentNetwork)) {
            $prepareRequest->setPaymentNetwork($paymentNetwork);
        }
        $prepareRequest->setCtrlCustomData(PayXpert::getCallbackAuthenticityData($prepareRequest->getOrder()->getId(), $customer->secure_key));

        // Merchant notifications
        if (Configuration::get('PAYXPERT_MERCHANT_NOTIF') === "true" && Configuration::get('PAYXPERT_MERCHANT_NOTIF_TO')) {
            $prepareRequest->setMerchantNotification(true);
            $prepareRequest->setMerchantNotificationTo(Configuration::get('PAYXPERT_MERCHANT_NOTIF_TO'));
            if (Configuration::get('PAYXPERT_MERCHANT_NOTIF_LANG')) {
                $prepareRequest->setMerchantNotificationLang(Configuration::get('PAYXPERT_MERCHANT_NOTIF_LANG'));
            }
        }

        $ctrlURLPrefix = Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://';

        if (version_compare(_PS_VERSION_, '1.5', '<')) {
            $prepareRequest->setCtrlCallbackURL($ctrlURLPrefix . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'modules/payxpert/validation.php');
            $prepareRequest->setCtrlRedirectURL($ctrlURLPrefix . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'order-confirmation.php?id_cart=' . (int) ($cart->id) . '&id_module=' . (int) ($this->id) . '&key=' . $customer->secure_key);
        } else {
            $prepareRequest->setCtrlCallbackURL($this->context->link->getModuleLink('payxpert', 'validation'));
            $prepareRequest->setCtrlRedirectURL($this->getModuleLinkCompat('payxpert', 'return', array('id_cart' => $cart->id)));
        }

        return $c2pClient->preparePayment($prepareRequest);
    }

    /**
     * Return array of product to fill Api Product properties
     *
     * @param Cart $cart
     * @return array
     */
    protected function getProductsApi($cart)
    {
        $products = array();

        foreach ($cart->getProducts() as $product) {
            $obj = new Product((int) $product['id_product']);
            $products[] = array( /* */
                'CartProductId' => $product['id_product'], /* */
                'CartProductName' => $product['name'], /* */
                'CartProductUnitPrice' => $product['price'], /* */
                'CartProductQuantity' => $product['quantity'], /* */
                'CartProductBrand' => $obj->manufacturer_name, /* */
                'CartProductMPN' => $product['ean13'], /* */
                'CartProductCategoryName' => $product['category'], /* */
                'CartProductCategoryID' => $product['id_category_default'] /* */
            );
        }

        return $products;
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();

            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_html .= $this->display(__FILE__, '/views/templates/admin/infos.tpl');

        if (version_compare(_PS_VERSION_, '1.6', '<')) {
            /* Prestashop parameter names must not exceed 32 chars for v < 1.6 */
            $this->assignSmartyVariable(
                'PAYXPERT_ORIGINATOR',
                Tools::safeOutput(Tools::getValue(
                    'PAYXPERT_ORIGINATOR',
                    Configuration::get('PAYXPERT_ORIGINATOR')
                ))
            );

            $this->assignSmartyVariable(
                'PAYXPERT_URL',
                Tools::safeOutput(Tools::getValue(
                    'PAYXPERT_URL',
                    Configuration::get('PAYXPERT_URL')
                ))
            );

            $merchantNotifications = (Configuration::get('PAYXPERT_MERCHANT_NOTIF') == "true") ? "true" : "false";
            if (Tools::getValue('PAYXPERT_MERCHANT_NOTIF')) {
                $merchantNotifications = (in_array(Tools::getValue('PAYXPERT_MERCHANT_NOTIF'), array("true", "1", "on"))) ? "true" : "false";
            }

            $this->assignSmartyVariable(
                'PAYXPERT_MERCHANT_NOTIF',
                $merchantNotifications
            );

            $this->assignSmartyVariable(
                'PAYXPERT_MERCHANT_NOTIF_TO',
                Tools::safeOutput(Tools::getValue(
                    'PAYXPERT_MERCHANT_NOTIF_TO',
                    Configuration::get('PAYXPERT_MERCHANT_NOTIF_TO')
                ))
            );

            $this->assignSmartyVariable(
                'PAYXPERT_MERCHANT_NOTIF_LANG',
                Tools::safeOutput(Tools::getValue(
                    'PAYXPERT_MERCHANT_NOTIF_LANG',
                    Configuration::get('PAYXPERT_MERCHANT_NOTIF_LANG')
                ))
            );

            $this->_html .= $this->display(
                __FILE__,
                '/views/templates/admin/config.tpl'
            );
        } else {
            $this->_html .= $this->renderForm();
        }

        return $this->_html;
    }

    public function getConfigFieldsValues()
    {
        // Handle checkboxes
        $merchantNotif = Tools::getValue('PAYXPERT_MERCHANT_NOTIF', Configuration::get('PAYXPERT_MERCHANT_NOTIF'));

        $result = array( /* */
            'PAYXPERT_ORIGINATOR' => Tools::getValue('PAYXPERT_ORIGINATOR', Configuration::get('PAYXPERT_ORIGINATOR')), /* */
            'PAYXPERT_URL' => Tools::getValue('PAYXPERT_URL', Configuration::get('PAYXPERT_URL')), /* */
            'PAYXPERT_MERCHANT_NOTIF' => ($merchantNotif === "true" || $merchantNotif == 1) ? 1 : 0, /* */
            'PAYXPERT_MERCHANT_NOTIF_TO' => Tools::getValue(
                'PAYXPERT_MERCHANT_NOTIF_TO',
                Configuration::get('PAYXPERT_MERCHANT_NOTIF_TO')
            ),
            'PAYXPERT_MERCHANT_NOTIF_LANG' => Tools::getValue(
                'PAYXPERT_MERCHANT_NOTIF_LANG',
                Configuration::get('PAYXPERT_MERCHANT_NOTIF_LANG')
            ),
        );

        if (version_compare(_PS_VERSION_, '1.6', '>=')) {
            $creditCardPaymentType = Tools::getValue(
                'PAYXPERT_PAYMENT_TYPE_CREDIT_CARD',
                Configuration::get('PAYXPERT_PAYMENT_TYPE_CREDIT_CARD')
            );

            $sofortPaymentType = Tools::getValue(
                'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_SOFORT',
                Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_SOFORT')
            );

            $przelewy24PaymentType = Tools::getValue(
                'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_PRZELEWY24',
                Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_PRZELEWY24')
            );

            $idealPaymentType = Tools::getValue(
                'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_IDEAL',
                Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_IDEAL')
            );

            $isIframe = Tools::getValue(
                'PAYXPERT_IS_IFRAME',
                Configuration::get('PAYXPERT_IS_IFRAME')
            );

            $result['PAYXPERT_PAYMENT_TYPE_CREDIT_CARD'] = ($creditCardPaymentType === "true" || $creditCardPaymentType == 1) ? 1 : 0;
            $result['PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_SOFORT'] = ($sofortPaymentType === "true" || $sofortPaymentType == 1) ? 1 : 0;
            $result['PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_PRZELEWY24'] = ($przelewy24PaymentType === "true" || $przelewy24PaymentType == 1) ? 1 : 0;
            $result['PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_IDEAL'] = ($idealPaymentType === "true" || $idealPaymentType == 1) ? 1 : 0;
            $result['PAYXPERT_IS_IFRAME'] = ($isIframe === "true" || $isIframe == 1) ? 1 : 0;
        }

        if (version_compare(_PS_VERSION_, '1.6', '>=') && version_compare(_PS_VERSION_, '1.7', '<')) {
            $weChatPaymentType = Tools::getValue(
                'PAYXPERT_PAYMENT_TYPE_WECHAT',
                Configuration::get('PAYXPERT_PAYMENT_TYPE_WECHAT')
            );

            $giropayPaymentType = Tools::getValue(
                'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_GIROPAY',
                Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_GIROPAY')
            );

            $alipayPaymentType = Tools::getValue(
                'PAYXPERT_PAYMENT_TYPE_ALIPAY',
                Configuration::get('PAYXPERT_PAYMENT_TYPE_ALIPAY')
            );

            $result['PAYXPERT_PAYMENT_TYPE_WECHAT'] = ($weChatPaymentType === "true" || $weChatPaymentType == 1) ? 1 : 0;
            $result['PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_GIROPAY'] = ($giropayPaymentType === "true" || $giropayPaymentType == 1) ? 1 : 0;
            $result['PAYXPERT_PAYMENT_TYPE_ALIPAY'] = ($alipayPaymentType === "true" || $alipayPaymentType == 1) ? 1 : 0;
        }

        return $result;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array( /* */
                'legend' => array( /* */
                    'title' => $this->l('Settings'), /* */
                    'icon' => 'icon-gears' /* */
                ), /* */
                'input' => array( /* */
                    array( /* */
                        'type' => 'text', /* */
                        'name' => 'PAYXPERT_ORIGINATOR', /* */
                        'label' => $this->l('Originator ID'), /* */
                        'desc' => $this->l('The identifier of your Originator'), /* */
                        'required' => true /* */
                    ),
                    array( /* */
                        'type' => 'password', /* */
                        'name' => 'PAYXPERT_PASSWORD', /* */
                        'label' => $this->l('Originator password'), /* */
                        'desc' => $this->l('The password associated with your Originator (leave empty to keep the current one)'), /* */
                        'hint' => $this->l('Leave empty to keep the current one'), /* */
                        'required' => false /* */
                    ),
                    array( /* */
                        'type' => 'text', /* */
                        'name' => 'PAYXPERT_URL', /* */
                        'label' => $this->l('Payment Page URL'), /* */
                        'desc' => $this->l('Leave this field empty unless you have been given an URL'), /* */
                        'required' => false /* */
                    ),
                    array( /* */
                        'type' => 'switch', /* */
                        'name' => 'PAYXPERT_MERCHANT_NOTIF', /* */
                        'label' => $this->l('Merchant notifications'), /* */
                        'desc' => $this->l('Whether or not to send a notification to the merchant for each processed payment'), /* */
                        'required' => false, /* */
                        'is_bool' => true, /* */
                        'values' => array( /* */
                            array('id' => 'notif_on', 'value' => 1, 'label' => $this->l('Enabled')), /* */
                            array('id' => 'notif_off', 'value' => 0, 'label' => $this->l('Disabled')) /* */
                        ) /* */
                    ),  /* */
                    array( /* */
                        'type' => 'text', /* */
                        'name' => 'PAYXPERT_MERCHANT_NOTIF_TO', /* */
                        'label' => $this->l('Merchant notifications recipient'), /* */
                        'desc' => $this->l('Recipient email address for merchant notifications'), /* */
                        'required' => false, /* */
                         'size' => 100 /* */
                    ), /* */
                    array( /* */
                        'type' => 'select', /* */
                        'name' => 'PAYXPERT_MERCHANT_NOTIF_LANG', /* */
                        'label' => $this->l('Merchant notifications lang'), /* */
                        'desc' => $this->l('Language to use for merchant notifications'), /* */
                        'required' => false, /* */
                        'options' => array( /* */
                            'query' => array( /* */
                                array('id_option' => 'en', 'name' => $this->l('English')), /* */
                                array('id_option' => 'fr', 'name' => $this->l('French')), /* */
                                array('id_option' => 'es', 'name' => $this->l('Spanish')), /* */
                                array('id_option' => 'it', 'name' => $this->l('Italian')) /* */
                            ), /* */
                            'id' => 'id_option', /* */
                            'name' => 'name' /* */
                        ) /* */
                    )
                ), /* */
                'submit' => array('title' => $this->l('Update settings')) /* */
            ) /* */
        );

        if (version_compare(_PS_VERSION_, '1.6', '>=') && version_compare(_PS_VERSION_, '1.7', '<')) {
            $fields_form['form']['input'][] = array( /* */
                'type' => 'switch', /* */
                'name' => 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_GIROPAY', /* */
                'label' => $this->l('Bank Transfer via Giropay'), /* */
                'desc' => $this->l('Enable payment type: Bank Transfer via Giropay'), /* */
                'required' => false, /* */
                'is_bool' => true, /* */
                'values' => array(/* */
                    array('id' => 'giropay_on', 'value' => 1, 'label' => $this->l('Enabled')), /* */
                    array('id' => 'giropay_off', 'value' => 0, 'label' => $this->l('Disabled')) /* */
                ) /* */
            );
            $fields_form['form']['input'][] = array( /* */
                'type' => 'switch', /* */
                'name' => 'PAYXPERT_PAYMENT_TYPE_WECHAT', /* */
                'label' => $this->l('WeChat Pay'), /* */
                'desc' => $this->l('Enable payment type: WeChat Pay'), /* */
                'required' => false, /* */
                'is_bool' => true, /* */
                'values' => array(/* */
                    array('id' => 'wechat_on', 'value' => 1, 'label' => $this->l('Enabled')), /* */
                    array('id' => 'wechat_off', 'value' => 0, 'label' => $this->l('Disabled')) /* */
                ) /* */
            );
            $fields_form['form']['input'][] = array( /* */
                'type' => 'switch', /* */
                'name' => 'PAYXPERT_PAYMENT_TYPE_ALIPAY', /* */
                'label' => $this->l('Alipay'), /* */
                'desc' => $this->l('Enable payment type: Alipay'), /* */
                'required' => false, /* */
                'is_bool' => true, /* */
                'values' => array(/* */
                    array('id' => 'alipay_on', 'value' => 1, 'label' => $this->l('Enabled')), /* */
                    array('id' => 'alipay_off', 'value' => 0, 'label' => $this->l('Disabled')) /* */
                ) /* */
            );
        }

        if (version_compare(_PS_VERSION_, '1.6', '>=')) {
            $fields_form['form']['input'][] = array( /* */
                'type' => 'switch', /* */
                'name' => 'PAYXPERT_PAYMENT_TYPE_CREDIT_CARD', /* */
                'label' => $this->l('Credit Card'), /* */
                'desc' => $this->l('Enable payment type: Credit Card'), /* */
                'required' => false, /* */
                'is_bool' => true, /* */
                'values' => array(/* */
                    array('id' => 'cc_on', 'value' => 1, 'label' => $this->l('Enabled')), /* */
                    array('id' => 'cc_off', 'value' => 0, 'label' => $this->l('Disabled')) /* */
                ) /* */
            );
            $fields_form['form']['input'][] = array( /* */
                'type' => 'switch', /* */
                'name' => 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_SOFORT', /* */
                'label' => $this->l('Bank Transfer via Sofort'), /* */
                'desc' => $this->l('Enable payment type: Bank Transfer via Sofort'), /* */
                'required' => false, /* */
                'is_bool' => true, /* */
                'values' => array(/* */
                    array('id' => 'sofort_on', 'value' => 1, 'label' => $this->l('Enabled')), /* */
                    array('id' => 'sofort_off', 'value' => 0, 'label' => $this->l('Disabled')) /* */
                ) /* */
            );
            $fields_form['form']['input'][] = array( /* */
                'type' => 'switch', /* */
                'name' => 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_PRZELEWY24', /* */
                'label' => $this->l('Bank Transfer via Przelewy24'), /* */
                'desc' => $this->l('Enable payment type: Bank Transfer via Przelewy24'), /* */
                'required' => false, /* */
                'is_bool' => true, /* */
                'values' => array(/* */
                    array('id' => 'przelewy24_on', 'value' => 1, 'label' => $this->l('Enabled')), /* */
                    array('id' => 'przelewy24_off', 'value' => 0, 'label' => $this->l('Disabled')) /* */
                ) /* */
            );
            $fields_form['form']['input'][] = array( /* */
                'type' => 'switch', /* */
                'name' => 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_IDEAL', /* */
                'label' => $this->l('Bank Transfer via iDeal'), /* */
                'desc' => $this->l('Enable payment type: Bank Transfer via iDeal'), /* */
                'required' => false, /* */
                'is_bool' => true, /* */
                'values' => array(/* */
                    array('id' => 'ideal_on', 'value' => 1, 'label' => $this->l('Enabled')), /* */
                    array('id' => 'ideal_off', 'value' => 0, 'label' => $this->l('Disabled')) /* */
                ) /* */
            );
            $iframeLabel = $this->l('Iframe mode');
            $iframeDesc = $this->l('Enable iframe mode');
            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                $iframeLabel = $this->l('Seamless mode');
                $iframeDesc = $this->l('Enable seamless mode');
            }

            $fields_form['form']['input'][] = array( /* */
                'type' => 'switch', /* */
                'name' => 'PAYXPERT_IS_IFRAME', /* */
                'label' => $iframeLabel, /* */
                'desc' => $iframeDesc, /* */
                'required' => false, /* */
                'is_bool' => true, /* */
                'values' => array(/* */
                    array('id' => 'iframe_on', 'value' => 1, 'label' => $this->l('Enabled')), /* */
                    array('id' => 'iframe_off', 'value' => 0, 'label' => $this->l('Disabled')) /* */
                ) /* */
            );
        }

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' .
            $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array( /* */
            'fields_value' => $this->getConfigFieldsValues(), /* */
            'languages' => $this->context->controller->getLanguages(), /* */
            'id_language' => $this->context->language->id /* */
        );

        return $helper->generateForm(array($fields_form));
    }

    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('PAYXPERT_ORIGINATOR')) {
                $this->_postErrors[] = $this->l('Originator is required.');
            }

            if (!Configuration::get('PAYXPERT_PASSWORD') && !Tools::getValue('PAYXPERT_PASSWORD')) {
                $this->_postErrors[] = $this->l('Password is required.');
            }

            if (in_array(Tools::getValue('PAYXPERT_MERCHANT_NOTIF'), array("true", "1", "on")) && !Tools::getValue('PAYXPERT_MERCHANT_NOTIF_TO')) {
                $this->_postErrors[] = $this->l('Merchant notifications recipient is required.');
            }

            if (Tools::getValue('PAYXPERT_MERCHANT_NOTIF_TO') && !Validate::isEmail(Tools::getValue('PAYXPERT_MERCHANT_NOTIF_TO'))) {
                $this->_postErrors[] = $this->l('Merchant notifications recipient must be a valid email address.');
            }

            if (!in_array(Tools::getValue('PAYXPERT_MERCHANT_NOTIF_LANG'), array("en", "fr", "es", "it"))) {
                $this->_postErrors[] = $this->l('Merchant notification lang is not valid.');
            }
        }
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('PAYXPERT_ORIGINATOR', Tools::getValue('PAYXPERT_ORIGINATOR'));

            if (Tools::getValue('PAYXPERT_PASSWORD')) {
                // Manually handle HTML special chars to avoid losing them
                Configuration::updateValue('PAYXPERT_PASSWORD', htmlentities(Tools::getValue('PAYXPERT_PASSWORD')));
            }

            Configuration::updateValue('PAYXPERT_URL', Tools::getValue('PAYXPERT_URL'));

            Configuration::updateValue('PAYXPERT_MERCHANT_NOTIF_TO', Tools::getValue('PAYXPERT_MERCHANT_NOTIF_TO'));

            if (in_array(Tools::getValue('PAYXPERT_MERCHANT_NOTIF_LANG'), array("en", "fr", "es", "it"))) {
                Configuration::updateValue('PAYXPERT_MERCHANT_NOTIF_LANG', Tools::getValue('PAYXPERT_MERCHANT_NOTIF_LANG'));
            }

            // Handle checkboxes
            $checkboxes = array( /* */
                'PAYXPERT_MERCHANT_NOTIF' /* */
            );
            if (version_compare(_PS_VERSION_, '1.6', '>=')) {
                $checkboxes[] = 'PAYXPERT_PAYMENT_TYPE_CREDIT_CARD';
                $checkboxes[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_SOFORT';
                $checkboxes[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_PRZELEWY24';
                $checkboxes[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_IDEAL';
                $checkboxes[] = 'PAYXPERT_IS_IFRAME';
            }

            if (version_compare(_PS_VERSION_, '1.6', '>=') && version_compare(_PS_VERSION_, '1.7', '<')) {
                $checkboxes[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_GIROPAY';
                $checkboxes[] = 'PAYXPERT_PAYMENT_TYPE_WECHAT';
                $checkboxes[] = 'PAYXPERT_PAYMENT_TYPE_ALIPAY';
            }

            foreach ($checkboxes as $checkbox) {
                if (in_array(Tools::getValue($checkbox), array("true", "1", "on"))) {
                    Configuration::updateValue($checkbox, "true");
                } else {
                    Configuration::updateValue($checkbox, "false");
                }
            }
        }

        if (version_compare(_PS_VERSION_, '1.6', '>=')) {
            $this->_html .= $this->displayConfirmation($this->l('Configuration updated'));
        } else {
            $this->_html .= '<span class="conf confirm"> ' . $this->l('Configuration updated') . '</span>';

            return true;
        }
    }

    private function checkPaymentTypeAndProvider($paymentType, $paymentNetwork)
    {
        // For Prestashop >=1.7, check that the payment type is enabled
        if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
            switch ($paymentType) {
                case PaymentMethod::CREDIT_CARD:
                    return Configuration::get('PAYXPERT_PAYMENT_TYPE_CREDIT_CARD') === "true";
                case PaymentMethod::BANK_TRANSFER:
                    if ($paymentNetwork !== null) {
                        switch ($paymentNetwork) {
                            case PaymentNetwork::SOFORT:
                                return Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_SOFORT') === "true";
                            case PaymentNetwork::PRZELEWY24:
                                return Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_PRZELEWY24') === "true";
                            case PaymentNetwork::IDEAL:
                                return Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_IDEAL') === "true";
                        }
                    }
                    break;
            }
        } else {
            return true;
        }

        return false;
    }

    private function checkCurrency($cart)
    {
        $currency_order = new Currency((int) ($cart->id_currency));
        $currencies_module = $this->getCurrency((int) $cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get PayXpert Url depending of the env
     *
     * @return string Url
     */
    public function getPayXpertUrl()
    {
        $url = Configuration::get('PAYXPERT_URL');

        if (Tools::strlen(trim($url)) <= 0) {
            $url = 'https://connect2.payxpert.com/';
        }

        return $url;
    }

    /**
     * Get the iframe config value
     *
     * @return boolean
     */
    public function isIframeMode()
    {
        $is_iframe = Configuration::get('PAYXPERT_IS_IFRAME');

        return $is_iframe === 'true' ? true : false;
    }

    /**
     * Returns the modules path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /* Theses functions are used to support all versions of Prestashop */
    public function assignSmartyVariable($name, $value)
    {
        // Check if context smarty variable is available
        if (isset($this->context->smarty)) {
            return $this->context->smarty->assign($name, $value);
        } else {
            // Use the global variable
            if (!isset($smarty)) {
                $smarty = $this->context->smarty;
            }

            return $smarty->assign($name, $value);
        }
    }

    public function getModuleLinkCompat($module, $controller = 'default', $params = null)
    {
        if (class_exists('Context')) {
            if (!$params) {
                $params = array();
            }

            return Context::getContext()->link->getModuleLink($module, $controller, $params);
        } else {
            if ($controller == 'default') {
                if ($params) {
                    $params = "?" . $params;
                }

                return Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http' . '://' . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . $module . '.php' .
                     $params;
            } else {
                return Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http' . '://' . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ .
                     'modules/payxpert/' . $controller . '.php';
            }
        }
    }

    public function getPageLinkCompat($controller, $ssl = null, $id_lang = null, $request = null, $request_url_encode = false, $id_shop = null)
    {
        if (class_exists('Context')) {
            return Context::getContext()->link->getPageLink($controller, $ssl, $id_lang, $request, $request_url_encode, $id_shop);
        } else {
            if ($controller == 'contact') {
                return Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http' . '://' . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'contact-form.php';
            } else {
                $params = (isset($params)) ? "?" . $params : "";

                return Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http' . '://' . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . $controller .
                     '.php' . $params;
            }
        }
    }

    public function addLog($message, $severity = 1, $errorCode = null, $objectType = null, $objectId = null, $allowDuplicate = true)
    {
        if (class_exists('PrestaShopLogger')) {
            PrestaShopLogger::addLog($message, $severity, $errorCode, $objectType, $objectId, $allowDuplicate);
        } else if (class_exists('Logger')) {
            Logger::addLog($message, $severity, $errorCode, $objectType, $objectId, $allowDuplicate);
        } else {
            error_log($message . "(" . $errorCode . ")");
        }
    }

    /* Callback authenticity check methods */
    public static function getCallbackAuthenticityData($orderId, $secure_key)
    {
        return sha1($orderId . $secure_key . html_entity_decode(Configuration::get('PAYXPERT_PASSWORD')));
    }

    public static function checkCallbackAuthenticityData($callbackData, $orderId, $secure_key)
    {
        return (strcasecmp($callbackData, PayXpert::getCallbackAuthenticityData($orderId, $secure_key)) === 0);
    }

    /* Theses functions are only used for Prestashop prior to version 1.5 */
    public function execPayment($cart)
    {
        if (!isset($cookie)) {
            $cookie = $this->context->cookie;
        }

        $this->assignSmartyVariable('nbProducts', $cart->nbProducts());
        $this->assignSmartyVariable('cust_currency', $cart->id_currency);
        $this->assignSmartyVariable('currencies', $this->getCurrency());
        $this->assignSmartyVariable('total', $cart->getOrderTotal(true, 3));
        $this->assignSmartyVariable('isoCode', Language::getIsoById((int)($cookie->id_lang)));
        $this->assignSmartyVariable('this_path', $this->_path);
        $this->assignSmartyVariable('this_link', $this->getModuleLinkCompat('payxpert', 'redirect'));
        $this->assignSmartyVariable('this_link_back', $this->getPageLinkCompat('order', true, null, "step=3"));

        return $this->display(__FILE__, '/views/templates/front/payment_execution.tpl');
    }

    public function displayErrorPage($message)
    {
        $this->assignSmartyVariable('errorMessage', $message);
        $this->assignSmartyVariable('this_link_back', $this->getPageLinkCompat('order', true, null, "step=3"));

        return $this->display(__FILE__, '/views/templates/front/payment_error.tpl');
    }
}
