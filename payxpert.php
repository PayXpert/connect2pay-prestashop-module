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

require_once(dirname(__FILE__) . '/lib/Connect2PayClient.php');

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
        $this->version = '1.1.1';
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

        // Add hook methods
        $hookResult = $this->registerHook('paymentReturn');
        if (version_compare(_PS_VERSION_, '1.7', '<')) {
            $hookResult = $hookResult && $this->registerHook('payment');
        } else {
            $hookResult = $hookResult && $this->registerHook('paymentOptions');
        }

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

        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_CREDIT_CARD';
            $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_SOFORT';
            $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_PRZELEWY24';
            $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_IDEAL';
            $moduleParameters[] = 'PAYXPERT_IS_IFRAME';
        }

        return $moduleParameters;
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
            $option->setCallToActionText($this->l('Pay by Credit Card'));
            $option->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    $controller,
                    array('payment_type' => PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_TYPE_CREDITCARD),
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
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_SOFORT') == "true") {
            $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $option->setCallToActionText($this->l('Pay by Bank Transfer via Sofort'));
            $option->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    $controller,
                    array(
                        'payment_type' => PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_TYPE_BANKTRANSFER,
                        'payment_provider' => PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_PROVIDER_SOFORT
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
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_PRZELEWY24') == "true") {
            $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $option->setCallToActionText($this->l('Pay by Bank Transfer via Przelewy24'));
            $option->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    $controller,
                    array(
                        'payment_type' => PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_TYPE_BANKTRANSFER,
                        'payment_provider' => PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_PROVIDER_PRZELEWY24
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
        if (Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_IDEAL') == "true") {
            $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $option->setCallToActionText($this->l('Pay by Bank Transfer via iDeal'));
            $option->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    $controller,
                    array(
                        'payment_type' => PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_TYPE_BANKTRANSFER,
                        'payment_provider' => PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_PROVIDER_IDEALKP
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

        $this->assignSmartyVariable(
            'this_link',
            $this->getModuleLinkCompat(
                'payxpert',
                'payment'
            )
        );

        if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
            $this->context->controller->addCSS($this->_path . 'views/css/payxpert.css');
        }

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
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
    public function redirect($cart, $paymentType = null, $paymentProvider = null)
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

        if ($paymentType == null || !PayXpert\Connect2Pay\C2PValidate::isPayment($paymentType)) {
            $paymentType = PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_TYPE_CREDITCARD;
        }

        if (!$this->checkPaymentTypeAndProvider($paymentType, $paymentProvider)) {
            return "Payment type or provider is not enabled";
        }

        $payment = $this->getPaymentClient($cart, $paymentType, $paymentProvider);

        // prepare API
        if ($payment->preparePayment() == false) {
            $message = "PayXpert : can't prepare transaction - " . $payment->getClientErrorMessage();
            $this->addLog($message, 3);
            return $message;
        }

        Tools::redirect($payment->getCustomerRedirectURL());
        exit();
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
    public function getPaymentClient($cart, $paymentType = null, $paymentProvider = null)
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
        $c2pClient = new PayXpert\Connect2Pay\Connect2PayClient(
            $this->getPayXpertUrl(),
            Configuration::get('PAYXPERT_ORIGINATOR'),
            html_entity_decode(Configuration::get('PAYXPERT_PASSWORD'))
        );

        // customer informations
        $c2pClient->setShopperID($cart->id_customer);
        $c2pClient->setShopperEmail($customer->email);
        $c2pClient->setShopperFirstName(Tools::substr($customer->firstname, 0, 35));
        $c2pClient->setShopperLastName(Tools::substr($customer->lastname, 0, 35));
        $c2pClient->setShopperCompany(Tools::substr($addr_invoice->company, 0, 128));
        $c2pClient->setShopperAddress(Tools::substr(trim($addr_invoice->address1 . ' ' . $addr_invoice->address2), 0, 255));
        $c2pClient->setShopperZipcode(Tools::substr($addr_invoice->postcode, 0, 10));
        $c2pClient->setShopperCity(Tools::substr($addr_invoice->city, 0, 50));
        $c2pClient->setShopperState(Tools::substr($invoice_state->name, 0, 30));
        $c2pClient->setShopperCountryCode($invoice_country->iso_code);
        $c2pClient->setShopperPhone(Tools::substr(trim($invoice_phone), 0, 20));

        // Shipping information
        $c2pClient->setShipToFirstName(Tools::substr($addr_delivery->firstname, 0, 35));
        $c2pClient->setShipToLastName(Tools::substr($addr_delivery->lastname, 0, 35));
        $c2pClient->setShipToCompany(Tools::substr($addr_delivery->company, 0, 128));
        $c2pClient->setShipToPhone(Tools::substr(trim($delivery_phone), 0, 20));
        $c2pClient->setShipToAddress(Tools::substr(trim($addr_delivery->address1 . " " . $addr_delivery->address2), 0, 255));
        $c2pClient->setShipToZipcode(Tools::substr($addr_delivery->postcode, 0, 10));
        $c2pClient->setShipToCity(Tools::substr($addr_delivery->city, 0, 50));
        $c2pClient->setShipToState(Tools::substr($delivery_state->name, 0, 30));
        $c2pClient->setShipToCountryCode($delivery_country->iso_code);
        $c2pClient->setShippingName(Tools::substr($carrier->name, 0, 50));
        $c2pClient->setShippingType(PayXpert\Connect2Pay\Connect2PayClient::_SHIPPING_TYPE_PHYSICAL);

        // Order informations
        $c2pClient->setOrderID(Tools::substr(pSQL($cart->id), 0, 100));
        $c2pClient->setOrderDescription(Tools::substr($this->l('Invoice:') . pSQL($cart->id), 0, 255));
        $c2pClient->setCustomerIP($_SERVER['REMOTE_ADDR']);
        $c2pClient->setCurrency($currency->iso_code);

        $total = number_format($cart->getOrderTotal(true, 3) * 100, 0, '.', '');

        $c2pClient->setAmount($total);
        $c2pClient->setOrderCartContent($this->getProductsApi($cart));
        $c2pClient->setPaymentMode(PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_MODE_SINGLE);
        $c2pClient->setPaymentType($paymentType);
        if ($paymentProvider != null && PayXpert\Connect2Pay\C2PValidate::isProvider($paymentProvider)) {
            $c2pClient->setProvider($paymentProvider);
        }
        $c2pClient->setCtrlCustomData(PayXpert::getCallbackAuthenticityData($c2pClient->getOrderID(), $customer->secure_key));

        // Merchant notifications
        if (Configuration::get('PAYXPERT_MERCHANT_NOTIF') === "true" && Configuration::get('PAYXPERT_MERCHANT_NOTIF_TO')) {
            $c2pClient->setMerchantNotification(true);
            $c2pClient->setMerchantNotificationTo(Configuration::get('PAYXPERT_MERCHANT_NOTIF_TO'));
            if (Configuration::get('PAYXPERT_MERCHANT_NOTIF_LANG')) {
                $c2pClient->setMerchantNotificationLang(Configuration::get('PAYXPERT_MERCHANT_NOTIF_LANG'));
            }
        }

        $ctrlURLPrefix = Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://';

        if (version_compare(_PS_VERSION_, '1.5', '<')) {
            $c2pClient->setCtrlCallbackURL($ctrlURLPrefix . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'modules/payxpert/validation.php');
            $c2pClient->setCtrlRedirectURL($ctrlURLPrefix . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'order-confirmation.php?id_cart=' . (int) ($cart->id) . '&id_module=' . (int) ($this->id) . '&key=' . $customer->secure_key);
        } else {
            $c2pClient->setCtrlCallbackURL($this->context->link->getModuleLink('payxpert', 'validation'));
            $c2pClient->setCtrlRedirectURL($this->getModuleLinkCompat('payxpert', 'return'));
        }

        return $c2pClient;
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

        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $creditCardPaymentType = Tools::getValue(
                'PAYXPERT_PAYMENT_TYPE_CREDIT_CARD',
                Configuration::get('PAYXPERT_PAYMENT_TYPE_CREDIT_CARD')
            );

            $sofortPaymentType = Tools::getValue(
                'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_SOFORT',
                Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_SOFORT')
            );

            $przelewy24PaymentType = Tools::getValue(
                'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_PRZELEWY24',
                Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_PRZELEWY24')
            );

            $idealPaymentType = Tools::getValue(
                'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_IDEAL',
                Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_IDEAL')
            );

            $isIframe = Tools::getValue(
                'PAYXPERT_IS_IFRAME',
                Configuration::get('PAYXPERT_IS_IFRAME')
            );

            $result['PAYXPERT_PAYMENT_TYPE_CREDIT_CARD'] = ($creditCardPaymentType === "true" || $creditCardPaymentType == 1) ? 1 : 0;
            $result['PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_SOFORT'] = ($sofortPaymentType === "true" || $sofortPaymentType == 1) ? 1 : 0;
            $result['PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_PRZELEWY24'] = ($przelewy24PaymentType === "true" || $przelewy24PaymentType == 1) ? 1 : 0;
            $result['PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_IDEAL'] = ($idealPaymentType === "true" || $idealPaymentType == 1) ? 1 : 0;
            $result['PAYXPERT_IS_IFRAME'] = ($isIframe === "true" || $isIframe == 1) ? 1 : 0;
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
                    ) /* */
                ), /* */
                'submit' => array('title' => $this->l('Update settings')) /* */
            ) /* */
        );

        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
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
                'name' => 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_SOFORT', /* */
                'label' => $this->l('Bank Transfert via Sofort'), /* */
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
                'name' => 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_PRZELEWY24', /* */
                'label' => $this->l('Bank Transfert via Przelewy24'), /* */
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
                'name' => 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_IDEAL', /* */
                'label' => $this->l('Bank Transfert via iDeal'), /* */
                'desc' => $this->l('Enable payment type: Bank Transfer via iDeal'), /* */
                'required' => false, /* */
                'is_bool' => true, /* */
                'values' => array(/* */
                    array('id' => 'ideal_on', 'value' => 1, 'label' => $this->l('Enabled')), /* */
                    array('id' => 'ideal_off', 'value' => 0, 'label' => $this->l('Disabled')) /* */
                ) /* */
            );
            $fields_form['form']['input'][] = array( /* */
                'type' => 'switch', /* */
                'name' => 'PAYXPERT_IS_IFRAME', /* */
                'label' => $this->l('Iframe mode'), /* */
                'desc' => $this->l('Enable iframe mode'), /* */
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
            if (version_compare(_PS_VERSION_, '1.7', '>=')) {
                $checkboxes[] = 'PAYXPERT_PAYMENT_TYPE_CREDIT_CARD';
                $checkboxes[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_SOFORT';
                $checkboxes[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_PRZELEWY24';
                $checkboxes[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_IDEAL';
                $checkboxes[] = 'PAYXPERT_IS_IFRAME';
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

    private function checkPaymentTypeAndProvider($paymentType, $paymentProvider)
    {
        // For Prestashop >=1.7, check that the payment type is enabled
        if (version_compare(_PS_VERSION_, '1.7.0', '>=') === true) {
            switch ($paymentType) {
                case PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_TYPE_CREDITCARD:
                    return Configuration::get('PAYXPERT_PAYMENT_TYPE_CREDIT_CARD') === "true";
                case PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_TYPE_BANKTRANSFER:
                    if ($paymentProvider !== null) {
                        switch ($paymentProvider) {
                            case PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_PROVIDER_SOFORT:
                                return Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_SOFORT') === "true";
                            case PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_PROVIDER_PRZELEWY24:
                                return Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_PRZELEWY24') === "true";
                            case PayXpert\Connect2Pay\Connect2PayClient::_PAYMENT_PROVIDER_IDEALKP:
                                return Configuration::get('PAYXPERT_PAYMENT_TYPE_BANK_TRANSFERT_IDEAL') === "true";
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
