<?php

use Payxpert\Classes\PayxpertConfiguration;
use Payxpert\Classes\PayxpertCronLog;
use Payxpert\Classes\PayxpertPaymentMethod;
use Payxpert\Classes\PayxpertPaymentToken;
use Payxpert\Classes\PayxpertPaymentTransaction;
use Payxpert\Classes\PayxpertSubscription;
use PayXpert\Connect2Pay\containers\constant\PaymentMethod;
use PayXpert\Connect2Pay\containers\constant\PaymentMode;
use Payxpert\Utils\Installer;
use Payxpert\Utils\Logger;
use Payxpert\Utils\Utils;
use Payxpert\Utils\Webservice;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

require_once __DIR__ . '/vendor/autoload.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class Payxpert extends PaymentModule
{
    public $bootstrap;

    /** @var string[] */
    public $hooks = [
        'actionAdminControllerSetMedia',
        'actionFrontControllerSetMedia',
        'dashboardData',
        'dashboardZoneOne',
        'displayAdminOrder',
        'displayCustomerAccount',
        'paymentOptions',
        'paymentReturn',
    ];

    public function __construct()
    {
        $this->name = 'payxpert';
        $this->tab = 'payments_gateways';
        $this->version = '2.0.0';
        $this->author = 'We+';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Payment Module - PayXpert Service');
        $this->description = $this->l('PayXpert Description');

        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
    }

    public function getModuleTabs()
    {
        return [
            // Main menu
            [
                'class_name' => 'AdminPayxpertMenu',
                'parent_class_name' => 'IMPROVE',
                'name' => [
                    'en' => 'PayXpert',
                ],
                'visible' => true,
                'icon' => 'apps',
            ],
            // Sub-menu configuration
            [
                'class_name' => 'AdminPayxpertConfiguration',
                'parent_class_name' => 'AdminPayxpertMenu',
                'name' => [
                    'en' => 'Configuration',
                ],
                'visible' => true,
                'icon' => 'settings',
            ],
            // Sub-menu transaction
            [
                'class_name' => 'AdminPayxpertTransaction',
                'parent_class_name' => 'AdminPayxpertMenu',
                'name' => [
                    'en' => 'Transactions',
                ],
                'visible' => true,
                'icon' => 'files',
            ],
            // Sub-menu subscription
            [
                'class_name' => 'AdminPayxpertSubscription',
                'parent_class_name' => 'AdminPayxpertMenu',
                'name' => [
                    'en' => 'Subscriptions',
                    'fr' => 'Abonnements',
                ],
                'visible' => true,
                'icon' => 'files',
            ],
        ];
    }

    public function install()
    {
        Logger::info('Launch');

        try {
            $install = parent::install() && Installer::install($this);
        } catch (Exception $e) {
            Logger::critical($e->getMessage());
            $this->_errors[] = $e->getMessage();
            $this->uninstall();

            return false;
        }

        if ($install) {
            Tools::clearSf2Cache();
            Logger::info('Installation complete');
        }

        return $install;
    }

    public function uninstall()
    {
        try {
            $uninstall = parent::uninstall() && Installer::uninstall($this);
        } catch (Exception $e) {
            Logger::critical($e->getMessage());
            $this->_errors[] = $e->getMessage();

            return false;
        }

        return $uninstall;
    }

    public function getContent()
    {
        return Tools::redirectAdmin($this->context->link->getAdminLink('AdminPayxpertConfiguration'));
    }

    public function getModuleDebugInfo()
    {
        $moduleName = $this->name;
        $overridePath = _PS_OVERRIDE_DIR_ . 'modules/' . $moduleName . '/';

        $shopID = Shop::isFeatureActive() && Shop::CONTEXT_ALL === Shop::getContext() ? 0 : Shop::getContextShopID();
        $configuration = PayxpertConfiguration::getCurrentObject($shopID);
        $isKeyValid = false;

        $context = Context::getContext();
        $shop = $context->shop;
        if (Shop::CONTEXT_ALL === $shop->getContext()) {
            $isOverriddenInTheme = $this->scanThemeOverride();
        } else {
            $themeName = $context->shop->theme->getName();
            $isOverriddenInTheme = $this->scanThemeOverride($themeName);
        }

        if (Validate::isLoadedObject($configuration)) {
            $accountInfo = Webservice::getAccountInfo($configuration->public_api_key, $configuration->private_api_key);
            if (isset($accountInfo['name'])) {
                $isKeyValid = true;
            }
        }

        return [
            '{php_version}' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION,
            '{cms_name}' => 'PrestaShop',
            '{cms_version}' => _PS_VERSION_,
            '{module_version}' => $this->version,
            '{is_overridden_in_override}' => is_dir($overridePath),
            '{is_overridden_in_theme}' => $isOverriddenInTheme,
            '{is_key_valid}' => $isKeyValid,
        ];
    }

    private function scanThemeOverride($themeName = null)
    {
        $isOverridden = false;
        $themesDir = _PS_ROOT_DIR_ . '/themes/';
        $themesToCheck = [];

        if (null !== $themeName) {
            $themesToCheck[] = $themeName;
        } else {
            // CONTEXT_ALL : check all themes
            foreach (scandir($themesDir) as $entry) {
                if ('.' === $entry || '..' === $entry) {
                    continue;
                }

                if (is_dir($themesDir . $entry) && file_exists($themesDir . $entry . '/config/theme.yml')) {
                    $themesToCheck[] = $entry;
                }
            }
        }

        foreach ($themesToCheck as $theme) {
            $overridePath = $themesDir . $theme . '/modules/' . $this->name;

            if (is_dir($overridePath)) {
                foreach (scandir($overridePath) as $file) {
                    if ('.' === $file || '..' === $file || 'mails' === $file) {
                        continue;
                    }
                    $isOverridden = true;
                    break 2;
                }
            }
        }

        return $isOverridden;
    }

    public function isUsingNewTranslationSystem()
    {
        return false;
    }

    public function hookActionAdminControllerSetMedia()
    {
        if ('AdminOrders' === Tools::getValue('controller') || 'AdminOrders' === Tools::getValue('tab')) {
            $this->context->controller->addCss(
                $this->_path . 'views/css/hook/display_admin_order.css',
                'all',
                null,
                false
            );
        }

        if ('AdminPayxpertTransaction' === Tools::getValue('controller')) {
            $this->context->controller->addCss(
                $this->_path . 'views/css/admin/transactions/index.css',
                'all',
                null,
                false
            );
        }

        if ('AdminPayxpertSubscription' === Tools::getValue('controller')) {
            $this->context->controller->addCss(
                $this->_path . 'views/css/admin/subscriptions/index.css',
                'all',
                null,
                false
            );
        }

        if ('AdminDashboard' === Tools::getValue('controller')) {
            $this->context->controller->addCss(
                $this->_path . 'views/css/hook/dashboard_zone_one.css',
                'all',
                null,
                false
            );

            $this->context->controller->addJs(
                $this->_path . 'views/js/hook/dashboard_zone_one.js'
            );

            Media::addJsDef([
                'pxpCronConfig' => [
                    'ajaxUrl'        => $this->context->link->getAdminLink('AdminPayxpertSubscription', true, [], ['ajax' => 1, 'action' => 'syncInstallment']),
                    'moduleName'     => $this->name,
                    'adminToken'     => Tools::getAdminTokenLite('AdminPayxpertSubscription'),
                    'defaultText'    => $this->l('Synchronize installments'),
                    'runningText'    => $this->l('Running...'),
                ],
            ]);
        }
    }

    public function hookActionFrontControllerSetMedia()
    {
        $configuration = PayxpertConfiguration::getCurrentObject();

        if (!$configuration) {
            return false;
        }

        if (in_array($this->context->controller->php_self, ['order', 'checkout'])) {
            Media::addJsDef([
                'oneclick' => $configuration->oneclick,
                'seamless' => PayxpertConfiguration::REDIRECT_MODE_SEAMLESS == $configuration->redirect_mode,
                'applepay' => $configuration->hasPaymentMethod('Applepay'),
                'applepay_logo_url' => Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment_method_logo/applepay.png'),
            ]);

            $this->context->controller->registerJavascript(
                'module-payxpert-checkout-js',
                'modules/payxpert/views/js/front/checkout.js',
                ['position' => 'bottom', 'priority' => 150]
            );

            $this->context->controller->registerJavascript(
                'applePayJS',
                'https://resources.sips-services.com/rsc/applepay/js/device_verification.js',
                ['server' => 'remote', 'position' => 'head']
            );

            $this->context->controller->registerStylesheet(
                'module-payxpert-checkout-css',
                'modules/payxpert/views/css/front/checkout.css'
            );
        }
    }

    public function hookdashboardData()
    {
        // Installments
        $installments = PayxpertSubscription::getNeedSynchronization();
        $nbInstallments = count($installments);

        // Cron LOGs
        $LOGs = PayxpertCronLog::getLast();
        $logHTML = $LOGs ? Utils::buildDashboardTaskExecutorHTML($LOGs, $this) : $this->l('No Task');
        
        return array(
            'data_value' => array(
                'payxpert_pending_installments' => $nbInstallments > 0 ? ('⚠️' . $nbInstallments) : '✔️',
                'payxpert_cron_logs' => $logHTML,
            )
        );
    }

    public function hookDashboardZoneOne()
    {
        return $this->display(__FILE__, 'views/templates/hook/dashboard_zone_one/information_block.tpl');
    }

    
    public function hookDisplayAdminOrder($params)
    {
        $order = new Order((int) $params['id_order']);

        if ($order->module != $this->name) {
            return;
        }

        $messages = [];
        $payByLinkSent = false;
        $configuration = PayxpertConfiguration::getCurrentObject($order->id_shop);

        if (Tools::isSubmit('submitAddRefund')) {
            $orderSlipID = Tools::getValue('order_slip_id');
            $transactionID = Tools::getValue('transaction_id');

            // Check $data validity
            try {
                if (false == $orderSlipID) {
                    throw new Exception($this->l('Order Slip ID not found.'));
                }

                if (false == $transactionID) {
                    throw new Exception($this->l('Transaction ID not found.'));
                }

                $orderSlip = new OrderSlip($orderSlipID);
                if (!Validate::isLoadedObject($orderSlip)) {
                    throw new Exception($this->l('Order Slip not found.'));
                }

                $transaction = new PayxpertPaymentTransaction($transactionID);

                if (!Validate::isLoadedObject($transaction)) {
                    throw new Exception($this->l('Transaction not found.'));
                }

                if (!in_array($transaction->operation, [PayxpertPaymentTransaction::OPERATION_SALE, PayxpertPaymentTransaction::OPERATION_CAPTURE])) {
                    throw new Exception($this->l('Transaction operation must be `sale` or `capture`.'));
                }
                $orderSlipAmount = (int) Tools::ps_round($orderSlip->amount * 100, 0, PS_ROUND_UP);
                $transactionAmount = (int) Tools::ps_round($transaction->amount * 100);
                $transactionsLinked = PayxpertPaymentTransaction::getByReferalTransactionIdAndResultCode(
                    $transaction->transaction_id,
                    PayxpertPaymentTransaction::RESULT_CODE_SUCCESS
                );

                $amountAlreadyRefunded = 0;
                if ($transactionsLinked) {
                    $amountAlreadyRefunded = array_sum(array_map(function ($t) {
                        return $t['amount'] * 100;
                    }, $transactionsLinked));
                }

                $refundableAmount = $transactionAmount - $amountAlreadyRefunded;
                $amountToRefund = min($orderSlipAmount, $refundableAmount);
                $refundResult = Webservice::refundTransaction($configuration, $transaction->transaction_id, $amountToRefund);

                if (isset($refundResult['error'])) {
                    Logger::critical($refundResult['error']);
                    throw new Exception($this->l('An error occured during the refund process : ') . $refundResult['error']);
                }

                if (PayxpertPaymentTransaction::RESULT_CODE_SUCCESS !== $refundResult['code']) {
                    Logger::critical($refundResult['message']);
                    throw new Exception($this->l('An error occured during the refund process : ') . $refundResult['message']);
                }

                $transactionInfo = Webservice::getTransactionInfo($configuration, $refundResult['transaction_id']);

                $payxpertPaymentTransaction = new PayxpertPaymentTransaction();
                $payxpertPaymentTransaction->hydrate($transactionInfo);
                $payxpertPaymentTransaction->id_shop = $order->id_shop;
                $payxpertPaymentTransaction->order_id = $order->id;
                $payxpertPaymentTransaction->order_slip_id = $orderSlip->id;
                $payxpertPaymentTransaction->transaction_referal_id = $transaction->transaction_id;
                $payxpertPaymentTransaction->save();

                if ($amountToRefund == $refundableAmount) {
                    $history = new OrderHistory();
                    $history->id_order = $order->id;
                    $history->changeIdOrderState(_PS_OS_REFUND_, $order->id);
                    $history->add();
                }

                $messages[] = ['type' => 'success', 'msg' => $this->l('Refund succeed.')];
            } catch (Exception $e) {
                $messages[] = ['type' => 'danger', 'msg' => $e->getMessage()];
            }
        }

        if (Tools::isSubmit('submitAddCapture')) {
            try {
                $transaction_id = Tools::getValue('id_payxpert_payment_transaction');
                $transaction = new PayxpertPaymentTransaction((int) $transaction_id);

                if (!Validate::isLoadedObject($transaction)) {
                    throw new Exception($this->l('Transaction not found.'));
                }

                if (PayxpertPaymentTransaction::OPERATION_AUTHORIZE != $transaction->operation) {
                    throw new Exception($this->l('Transaction operation must be `authorize`'));
                }

                $hasAtransactionLinked = PayxpertPaymentTransaction::isExistByTransactionReferalId($transaction->transaction_id);
                if ($hasAtransactionLinked) {
                    throw new Exception($this->l('A capture transaction already exist for this transaction ID.'));
                }

                $captureInfo = Webservice::captureTransaction(
                    $configuration,
                    $transaction->transaction_id,
                    (int) Tools::ps_round($transaction->amount * 100)
                );

                if (isset($captureInfo['error'])) {
                    Logger::critical($captureInfo['error']);
                    throw new Exception($this->l('An error occured during the capture process : ') . $captureInfo['error']);
                }

                if (PayxpertPaymentTransaction::RESULT_CODE_SUCCESS !== $captureInfo['code']) {
                    Logger::critical($captureInfo['message']);
                    throw new Exception($this->l('An error occured during the capture process : ') . $captureInfo['message']);
                }

                $transactionInfo = Webservice::getTransactionInfo($configuration, $captureInfo['transaction_id']);
                $payxpertPaymentTransaction = new PayxpertPaymentTransaction();
                $payxpertPaymentTransaction->hydrate($transactionInfo);
                $payxpertPaymentTransaction->id_shop = $order->id_shop;
                $payxpertPaymentTransaction->order_id = $order->id;
                $payxpertPaymentTransaction->transaction_referal_id = $transaction->transaction_id;
                $payxpertPaymentTransaction->save();

                $history = new OrderHistory();
                $history->id_order = $order->id;
                $history->changeIdOrderState(_PS_OS_PAYMENT_, $order->id, true);
                $history->add();

                $messages[] = ['type' => 'success', 'msg' => $this->l('Capture succeed.')];
            } catch (Exception $e) {
                $messages[] = ['type' => 'danger', 'msg' => $e->getMessage()];
            }
        }

        $osWaitingPaybylink = Configuration::getGlobalValue('OS_PAYXPERT_WAITING_PAYBYLINK') == $order->current_state;
        $isPayByLinkEnable = $configuration->paybylink;
        $minAmountOK = $configuration->instalment_payment_min_amount < $order->total_paid;
        $instalmentOK = $minAmountOK && $isPayByLinkEnable && $osWaitingPaybylink;

        $configurationPaymentMethods = $configuration->getConfigurationPaymentMethods();
        $instalmentPaymentAvailable = [];
        foreach ($configurationPaymentMethods as $configurationPaymentMethod) {
            if (!$configurationPaymentMethod->active) {
                continue;
            }

            $paymentMethod = new PayxpertPaymentMethod($configurationPaymentMethod->payment_method_id);
            $paymentMethodConfig = json_decode($paymentMethod->config, true);

            if (isset($paymentMethodConfig['payment_mode']) && PaymentMode::INSTALMENTS == $paymentMethodConfig['payment_mode']) {
                $instalmentPaymentAvailable[$configurationPaymentMethod->payment_method_id] = $paymentMethodConfig;

                if ($instalmentOK) {
                    $this->context->smarty->assign($paymentMethodConfig['instalment_configuration'], $configurationPaymentMethod->payment_method_id);
                }
            }
        }

        if ($order->current_state == Configuration::getGlobalValue('OS_PAYXPERT_WAITING_PAYBYLINK')) {
            // Check if mail has not already be sent
            $payByLinkSent = PayxpertPaymentToken::existsRecentPaybylinkForIdCart($order->id_cart, true);
        }

        if (Tools::isSubmit('submitAddPaybylink')) {
            try {
                if ($payByLinkSent) {
                    throw new Exception($this->l('An email has already be sent for this order'));
                }

                if (!$osWaitingPaybylink) {
                    throw new Exception($this->l('The order status must be `Waiting for paybylink payment (PayXpert)`'));
                }

                $paymentMethodId = Tools::getValue('payxpert_payment_method');
                $paymentMode = PaymentMode::SINGLE;
                $instalmentParameters = [];
                if (isset($instalmentPaymentAvailable[$paymentMethodId])) {
                    $instalmentParameters = isset($instalmentPaymentAvailable[$paymentMethodId]['instalment_configuration']) ? [
                        'firstPercentage' => $configuration->{$instalmentPaymentAvailable[$paymentMethodId]['instalment_configuration']},
                        'xTimes' => $instalmentPaymentAvailable[$paymentMethodId]['instalment_x_times'],
                    ] : [];
                    $paymentMode = $instalmentPaymentAvailable[$paymentMethodId]['payment_mode'];
                }

                $preparedPayment = Webservice::preparePayment(
                    $configuration,
                    PaymentMethod::CREDIT_CARD,
                    new Cart($order->id_cart),
                    $paymentMode,
                    $instalmentParameters,
                    true
                );

                if (isset($preparedPayment['error'])) {
                    throw new Exception($preparedPayment['error']);
                }

                $orderCustomer = new Customer($order->id_customer);
                $deadline = new DateTime($order->date_add);
                $deadline->modify('+30 days');

                $mailSent = Mail::Send(
                    (int) $this->context->language->id,
                    'pay_by_link',
                    'PAYBYLINK Subject',
                    [
                        '{firstname}' => $orderCustomer->firstname,
                        '{lastname}' => $orderCustomer->lastname,
                        '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                        '{order_reference}' => $order->reference,
                        '{payment_link}' => $preparedPayment['redirectUrl'],
                        '{order_date}' => $order->date_add,
                        '{order_products}' => Utils::generateOrderProductsHTML($order),
                        '{order_subtotal}' => Tools::displayPrice($order->total_products_wt),
                        '{order_shipping}' => Tools::displayPrice($order->total_shipping_tax_incl),
                        '{order_total}' => Tools::displayPrice($order->total_paid),
                        '{payment_deadline}' => $deadline->format('Y-m-d H:i:s'),
                        '{shop_url}' => $this->context->shop->getBaseURL(),
                    ],
                    $orderCustomer->email,
                    $orderCustomer->lastname . ' ' . $orderCustomer->firstname,
                    null,
                    null,
                    null,
                    null,
                    _PS_MODULE_DIR_ . 'payxpert/mails/'
                );

                if (!$mailSent) {
                    // If failure, delete the paymentToken so we don't forbide further retry
                    $preparedPayment['paymentToken']->delete();
                    throw new Exception($this->l('Failed to send the paybylink email. Please try again.'));
                }

                $payByLinkSent = true;
                $messages[] = ['type' => 'success', 'msg' => $this->l('The paybylink email has been sent to the customer')];
            } catch (Exception $e) {
                $messages[] = ['type' => 'danger', 'msg' => $e->getMessage()];
            }
        }

        $transactions = PayxpertPaymentTransaction::getAllByOrderId($order->id);
        $iterationsLeft = null;
        $needSync = false;

        if (!empty($transactions)) {
            if ($transactions[0]['subscription_id'] != null) {
                $subscriptionInfo = Webservice::getStatusSubscription($configuration, $transactions[0]['subscription_id']);
                if (!isset($subscriptionInfo['error'])) {
                    $iterationsLeft = $subscriptionInfo['subscription']['iterationsLeft'];
                    $needSync = (count($subscriptionInfo['transactionList']) - count($transactions)) > 0;
                }
            }
        }

        if ($needSync) {
            if (empty($transactions)) {
                $messages[] = ['type' => 'danger', 'msg' => $this->l('No transaction found')];
            } else {
                if (!isset($subscriptionInfo)) {
                    $messages[] = ['type' => 'danger', 'msg' => $this->l('Initial transaction is not an installment payment')];
                } else {
                    if (isset($subscriptionInfo['error'])) {
                        $messages[] = ['type' => 'danger', 'msg' => $subscriptionInfo['error']];
                    } else {
                        if (Utils::syncSubscription($subscriptionInfo, $transactions[0])) {
                            $messages[] = ['type' => 'info', 'msg' => $this->l('Installment payments for this order have been synchronized')];
                            // Reset $transactions
                            $transactions = PayxpertPaymentTransaction::getAllByOrderId($order->id);
                        }
                    }
                }
            }
        }

        $orderTransactionsFormatted = Utils::getOrderTransactionsFormatted($transactions);
        $orderSlips = $order->getOrderSlipsCollection()->getResults();
        $orderSlips = array_filter($orderSlips, function ($orderSlip) use ($orderTransactionsFormatted) {
            return !in_array($orderSlip->id, $orderTransactionsFormatted['order_slip_used']);
        });

        if (!empty($orderTransactionsFormatted['refundable']) && !empty($orderSlips)) {
            $orderSlipChoices = [];
            foreach ($orderSlips as $orderSlip) {
                $orderSlip = (array) $orderSlip;
                $orderSlipChoices[] = [
                    'id' => $orderSlip['id'],
                    'name' => '#' . $orderSlip['id'] . ' | ' . $this->l('Amount') . ': ' . Tools::ps_round($orderSlip['amount'], _PS_PRICE_COMPUTE_PRECISION_, PS_ROUND_UP),
                ];
            }

            $transactionChoices = [];
            foreach ($transactions as $transaction) {
                $transactionChoices[] = [
                    'id' => $transaction['id_payxpert_payment_transaction'],
                    'name' => '#' . $transaction['transaction_id'] . ' | ' . $this->l('Amount') . ': ' . Tools::ps_round($transaction['amount'], _PS_PRICE_COMPUTE_PRECISION_) . ' ' . $transaction['currency'],
                ];
            }

            $this->context->smarty->assign([
                'orderSlipChoices' => $orderSlipChoices,
                'transactionChoices' => $transactionChoices,
            ]);

            $refundForm = $this->display(__FILE__, 'views/templates/hook/display_admin_order/refund_form.tpl');
        }

        $this->context->smarty->assign([
            'payxpert_messages' => $messages,
            'module_dir' => _MODULE_DIR_ . 'payxpert/',
            'transactions' => $transactions,
            'refundable_transactions' => $orderTransactionsFormatted['refundable'],
            'logo_path' => Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/logo.png'),
            'has_order_slips' => count($orderSlips) > 0,
            'is_refund' => _PS_OS_REFUND_ == $order->current_state,
            'transaction_refund_form' => isset($refundForm) ? $refundForm : null,
            'display_paybylink' => $isPayByLinkEnable && $osWaitingPaybylink && $configuration->hasPaymentMethod(Utils::CREDIT_CARD),
            'display_paybylink_x2' => $instalmentOK && $configuration->hasPaymentMethod(Utils::CREDIT_CARD_X2),
            'display_paybylink_x3' => $instalmentOK && $configuration->hasPaymentMethod(Utils::CREDIT_CARD_X3),
            'display_paybylink_x4' => $instalmentOK && $configuration->hasPaymentMethod(Utils::CREDIT_CARD_X4),
            'paybylink_sent' => $payByLinkSent,
            'code_success' => PayxpertPaymentTransaction::RESULT_CODE_SUCCESS,
            'liability_shift_ok' => PayxpertPaymentTransaction::LIABILITY_SHIFT_OK,
            'capturable_transaction_ids' => array_keys($orderTransactionsFormatted['capturable']),
            'iterationsLeft' => $iterationsLeft,
            'needSync' => $needSync,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/display_admin_order/transactions.tpl');
    }

    public function hookDisplayCustomerAccount($params)
    {
        $this->context->smarty->assign([
            'module_dir' => $this->_path,
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/hook/display_customer_account/my-account.tpl');
    }

    public function hookPaymentOptions($params)
    {
        $paymentOptions = [];
        $cart = $params['cart'];

        // Fix error on checkout step - Address
        if (0 == $cart->id_address_delivery) {
            return $paymentOptions;
        }

        try {
            $amount = $cart->getOrderTotal();
            $configuration = PayxpertConfiguration::getCurrentObject();

            if (!$configuration) {
                Logger::critical('Configuration not found');

                return $paymentOptions;
            }

            if (!$configuration->active) {
                return $paymentOptions;
            }

            $applepayEnable = $configuration->hasPaymentMethod('Applepay');
            $redirectMode = $configuration->redirect_mode;
            $configurationPaymentMethods = $configuration->getConfigurationPaymentMethods();
            $configurationPaymentMethodsLang = Utils::formatConfigurationPaymentMethodsLang($configuration->getPaymentMethodsLang());

            foreach ($configurationPaymentMethods as $configurationPaymentMethod) {
                if (!$configurationPaymentMethod->active) {
                    continue;
                }

                $paymentMethod = new PayxpertPaymentMethod($configurationPaymentMethod->payment_method_id);
                $paymentMethodConfig = json_decode($paymentMethod->config, true);

                if (
                    !Utils::isPaymentMethodAvailableForCurrency($cart->id_currency, $paymentMethodConfig)
                    || !Utils::isPaymentMethodAvailableForCountry($cart->id_address_invoice, $paymentMethodConfig)
                    || !Utils::isPaymentMethodAvailableForFrontOffice($paymentMethodConfig)
                    || !Utils::isPaymentMethodAvailableForBackOffice($paymentMethodConfig)
                ) {
                    continue;
                }

                $paymentOption = new PaymentOption();
                $paymentOption->setModuleName($this->name);
                $paymentOption->setCallToActionText($configurationPaymentMethodsLang[$paymentMethod->id_payxpert_payment_method][$this->context->language->id]);
                $paymentOption->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true));

                $inputs = [
                    'payment_method' => [
                        'name' => 'payxpert_payment_method',
                        'type' => 'hidden',
                        'value' => $paymentMethod->id_payxpert_payment_method,
                    ],
                ];

                $pMode = $paymentMethodConfig['payment_mode'] ?? PaymentMode::SINGLE;
                $pMethode = $paymentMethodConfig['payment_method'] ?? PaymentMethod::CREDIT_CARD;

                // 18px height max for classic display
                if (isset($paymentMethodConfig['logo_img'])) {
                    $paymentOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment_method_logo/' . $paymentMethodConfig['logo_img']));
                    
                    // Special case for Amex
                    if ($pMethode == PaymentMethod::CREDIT_CARD && $configuration->amex) {
                        $logoImg = preg_replace('/(\.[a-z0-9]+)$/i', '_with_amex$1', $paymentMethodConfig['logo_img']);
                        $paymentOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/payment_method_logo/' . $logoImg));
                    }
                }

                /* Special features for SIMPLE credit card payment method */
                if (Utils::CREDIT_CARD == $paymentMethod->name && PayxpertConfiguration::REDIRECT_MODE_REDIRECT == $redirectMode && $configuration->oneclick) {
                    $inputs['register_card'] = [
                        'name' => 'payxpert_oneclick_register_card',
                        'type' => 'hidden',
                        'value' => 0,
                    ];

                    $paymentOption->setAdditionalInformation($this->context->smarty->fetch($this->local_path . 'views/templates/hook/payment_options/oneclick.tpl'));
                }

                /* Keep it before seamless checkout */
                $setAdditionnalInfoInstalment = false;
                if (PaymentMode::INSTALMENTS == $pMode) {
                    if (!isset($paymentMethodConfig['instalment_configuration']) || $amount < $configuration->instalment_payment_min_amount) {
                        continue;
                    }

                    if (!$configuration->instalment_logo_active) {
                        $paymentOption->setLogo(null);
                    }

                    $schedule = Utils::buildInstalmentSchedule(
                        $amount,
                        $configuration->{$paymentMethodConfig['instalment_configuration']},
                        $paymentMethodConfig['instalment_x_times'],
                        $cart->id_currency
                    );

                    $this->context->smarty->assign([
                        'schedule' => $schedule
                    ]);

                    $setAdditionnalInfoInstalment = true;
                }

                /* Seamless checkout for credit card payment method */
                if ($pMethode == PaymentMethod::CREDIT_CARD && PayxpertConfiguration::REDIRECT_MODE_SEAMLESS == $redirectMode) {
                    // PreparePayment
                    $preparedPayment = Webservice::preparePayment(
                        $configuration,
                        $pMethode,
                        $params['cart'],
                        $pMode,
                        $setAdditionnalInfoInstalment ? [
                            'firstPercentage' => $configuration->{$paymentMethodConfig['instalment_configuration']},
                            'xTimes' => $paymentMethodConfig['instalment_x_times']
                        ] : []
                    );

                    if (isset($preparedPayment['error'])) {
                        Logger::critical($preparedPayment['error']);
                        continue;
                    }

                    $this->context->smarty->assign([
                        'customerToken' => $preparedPayment['customerToken'],
                        'uniqueContainerId' => Tools::passwdGen('20', 'NUMERIC'),
                        'applepay' => $applepayEnable,
                        'ajaxUrl' => $this->context->link->getModuleLink('payxpert', 'ajax'),
                        'payButtonAmount' => Tools::displayPrice(number_format($cart->getOrderTotal(), 2))
                    ]);

                    $inputs += [
                        'seamless' => [
                            'name' => 'seamless',
                            'type' => 'hidden',
                            'value' => 1,
                        ],
                    ];

                    if ($setAdditionnalInfoInstalment) {
                        $this->context->smarty->assign([
                            'seamless' => true,
                            'payButtonAmount' => Tools::displayPrice(number_format($schedule[0]['amount'], 2))
                        ]);
                    } else {
                        $paymentOption->setAdditionalInformation($this->context->smarty->fetch($this->local_path . 'views/templates/hook/payment_options/seamless.tpl'));
                    }
                }

                if ($setAdditionnalInfoInstalment) {
                    $paymentOption->setAdditionalInformation($this->context->smarty->fetch($this->local_path . 'views/templates/hook/payment_options/instalment.tpl'));
                }

                $paymentOption->setInputs($inputs);
                $paymentOptions[] = $paymentOption;
            }
        } catch (Exception $e) {
            Logger::critical($e->getMessage());
        }

        return $paymentOptions;
    }

    public function hookPaymentReturn($params)
    {
    }
}
