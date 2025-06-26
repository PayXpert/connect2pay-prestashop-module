<?php

declare(strict_types=1);

use Payxpert\Classes\PayxpertConfiguration;
use Payxpert\Classes\PayxpertConfigurationPaymentMethod;
use Payxpert\Classes\PayxpertPaymentMethod;
use Payxpert\Classes\PayxpertPaymentMethodLang;
use Payxpert\Utils\Logger;
use Payxpert\Utils\Utils;
use Payxpert\Utils\Webservice;

class AdminPayxpertConfigurationController extends ModuleAdminController
{
    public $paymentMethods;

    public function __construct()
    {
        $this->table = PayxpertConfiguration::$definition['table'];
        $this->className = PayxpertConfiguration::class;
        $this->display = 'edit';
        $this->bootstrap = true;
        $this->multiple_fieldsets = true;

        parent::__construct();
    }

    public function init()
    {
        parent::init();

        if (!$this->ajax && !method_exists($this, 'process' . ucfirst(Tools::toCamelCase($this->action)))) {
            $shopID = Shop::isFeatureActive() && Shop::CONTEXT_ALL === Shop::getContext() ? 0 : Shop::getContextShopID();
            $idPayxpertConfiguration = Tools::getValue('id_payxpert_configuration');
            $configuration = PayxpertConfiguration::getCurrentObject($shopID);

            if (!$configuration && $idPayxpertConfiguration) {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminPayxpertConfiguration'));
            }

            if ($configuration && (!$idPayxpertConfiguration || $idPayxpertConfiguration != $configuration->id_payxpert_configuration)) {
                Tools::redirectAdmin(
                    $this->context->link->getAdminLink(
                        'AdminPayxpertConfiguration',
                        true,
                        [],
                        [
                            'id_payxpert_configuration' => $configuration->id_payxpert_configuration,
                        ]
                    )
                );
            }
        }

        $this->paymentMethods = PayxpertPaymentMethod::getAll();
    }

    public function renderForm()
    {
        $this->fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Your merchant account', 'adminpayxpertconfiguration'),
                'icon' => 'icon-cogs',
            ],
            'input' => [
                // API key public
                [
                    'type' => 'text',
                    'label' => $this->l('API Public Key', 'adminpayxpertconfiguration'),
                    'name' => 'public_api_key',
                    'size' => 255,
                    'required' => true,
                    'col' => 3,
                    'hint' => $this->l('The Public Key will be made available by PayXpert by email, once the contract has been validated.', 'adminpayxpertconfiguration')
                ],
                [
                    'type' => 'password',
                    'label' => $this->l('API Private Key', 'adminpayxpertconfiguration'),
                    'name' => 'private_api_key',
                    'size' => 255,
                    'required' => true,
                    'col' => 3,
                    'hint' => $this->l('The Private Key will be made available by PayXpert by email, once the contract has been validated.', 'adminpayxpertconfiguration')
                ],
            ],
            'submit' => [
                'title' => $this->l('Save', 'adminpayxpertconfiguration'),
            ],
        ];

        $showAdditionalFields = (bool) $this->id_object;

        if ($showAdditionalFields) {
            $this->fields_form[0]['form']['input'][] = [
                'type' => 'switch',
                'label' => $this->l('Active', 'adminpayxpertconfiguration'),
                'name' => 'active',
                'is_bool' => true,
                'required' => true,
                'values' => [
                    [
                        'id' => 'active_on',
                        'value' => 1,
                    ],
                    [
                        'id' => 'active_off',
                        'value' => 0,
                    ],
                ],
                'help' => $this->l('Enable or disable the module.', 'adminpayxpertconfiguration'),
            ];

            $configurationPaymentMethodsLangs = PayxpertPaymentMethodLang::getAll($this->id_object);
            $paymentLabels = [];
            foreach ($configurationPaymentMethodsLangs as $configurationPaymentMethodsLang) {
                $paymentLabels[$configurationPaymentMethodsLang['payment_method_id']][$configurationPaymentMethodsLang['id_lang']] = $configurationPaymentMethodsLang['name'];
            }

            $languagesID = Language::getLanguages(true, false, true);
            $paymentMethodsInputs = [];
            $paymentMethodsLangInputs = [];
            $paymentMethodsTranslations = [
                Utils::CREDIT_CARD => [
                    'label' => $this->l('CB/Visa/Mastercard card', 'adminpayxpertconfiguration')
                ],
                Utils::CREDIT_CARD_X2 => [
                    'label' => $this->l('Payment by card in 2 installments', 'adminpayxpertconfiguration'), 
                    'hint' => $this->l('When paying in installments, only the first transaction is guaranteed for the merchant. Subsequent transactions are therefore riskier by default.', 'adminpayxpertconfiguration')
                ],
                Utils::CREDIT_CARD_X3 => [
                    'label' => $this->l('Payment by card in 3 installments', 'adminpayxpertconfiguration'), 
                    'hint' => $this->l('When paying in installments, only the first transaction is guaranteed for the merchant. Subsequent transactions are therefore riskier by default.', 'adminpayxpertconfiguration')
                ],
                Utils::CREDIT_CARD_X4 => [
                    'label' => $this->l('Payment by card in 4 installments', 'adminpayxpertconfiguration'), 
                    'hint' => $this->l('When paying in installments, only the first transaction is guaranteed for the merchant. Subsequent transactions are therefore riskier by default.', 'adminpayxpertconfiguration')
                ],
                Utils::ALIPAY => ['hint' => $this->l('Accessible by scanning the QR code generated by the payment page – Specific acceptance fees by Alipay+', 'adminpayxpertconfiguration')],
                Utils::WECHAT => ['hint' => $this->l('Accessible by scanning the QR code generated by the payment page – Specific acceptance fee by WeChat Pay', 'adminpayxpertconfiguration')],
                Utils::APPLEPAY => ['hint' => $this->l('This payment method will be displayed provided that the customer uses an Apple environment (Browser and device)', 'adminpayxpertconfiguration')],
            ];

            foreach ($this->paymentMethods as $paymentMethod) {
                $config = json_decode($paymentMethod['config'], true);

                $paymentMethodsInputs[] = [
                    'type' => 'switch',
                    'label' => $paymentMethodsTranslations[$paymentMethod['name']]['label'] ?? $paymentMethod['name'],
                    'name' => 'payment_method_' . $paymentMethod['id_payxpert_payment_method'],
                    'is_bool' => true,
                    'required' => true,
                    'attr' => ['data-toggle' => isset($config['toggle']) ? $config['toggle'] : null],
                    'values' => [
                        [
                            'id' => $paymentMethod['name'] . '_on',
                            'value' => 1,
                        ],
                        [
                            'id' => $paymentMethod['name'] . '_off',
                            'value' => 0,
                        ],
                    ],
                    'hint' => $paymentMethodsTranslations[$paymentMethod['name']]['hint'] ?? null,
                ];

                $paymentMethodsLangInputs[] = [
                    'type' => 'text',
                    'size' => 100,
                    'label' => $this->l('Payment Label : ', 'adminpayxpertconfiguration') . $paymentMethod['name'],
                    'name' => 'payment_method_lang_' . $paymentMethod['id_payxpert_payment_method'],
                    'required' => true,
                    'lang' => true,
                    'col' => 6,
                ];

                // Fields value paymentLabel
                foreach ($languagesID as $languageID) {
                    $this->fields_value['payment_method_lang_' . $paymentMethod['id_payxpert_payment_method']][$languageID] = $paymentLabels[$paymentMethod['id_payxpert_payment_method']][$languageID] ?? $config['default_payment_label']['en'];
                }
            }

            // Special add for AMEX but it is not a paymentMethod
            array_splice($paymentMethodsInputs, 1, 0, [[
                'type' => 'switch',
                'label' => 'American Express',
                'name' => 'amex',
                'is_bool' => true,
                'required' => true,
                'values' => [
                    [
                        'id' => 'amex_on',
                        'value' => 1,
                    ],
                    [
                        'id' => 'amex_off',
                        'value' => 0,
                    ],
                ],
            ]]);

            // PaymentMethod
            $this->fields_form[1]['form'] = [
                'legend' => [
                    'title' => $this->l('Payment methods', 'adminpayxpertconfiguration'),
                    'icon' => 'icon-cogs',
                ],
                'input' => $paymentMethodsInputs,
                'submit' => [
                    'title' => $this->l('Save', 'adminpayxpertconfiguration'),
                ],
            ];

            // Payment parameters
            $this->fields_form[2]['form'] = [
                'legend' => [
                    'title' => $this->l('Payment parameters', 'adminpayxpertconfiguration'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Capture mode', 'adminpayxpertconfiguration'),
                        'name' => 'capture_mode',
                        'required' => true,
                        'options' => [
                            'query' => [
                                [
                                    'id' => PayxpertConfiguration::CAPTURE_MODE_AUTOMATIC,
                                    'name' => $this->l('Automatic', 'adminpayxpertconfiguration'),
                                ],
                                [
                                    'id' => PayxpertConfiguration::CAPTURE_MODE_MANUAL,
                                    'name' => $this->l('Manual', 'adminpayxpertconfiguration'),
                                ],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('To secure payments, the latency period for manual capture is limited to 7 days. It is imperative to capture transactions before this deadline.', 'adminpayxpertconfiguration'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Email notification', 'adminpayxpertconfiguration'),
                        'name' => 'capture_manual_email',
                        'size' => 64,
                        'required' => false,
                        'desc' => $this->l('Enter the email address that will be notified before the automatic initiation of the capture (5 days after the transaction).', 'adminpayxpertconfiguration'),
                        'alert_message' => $this->l('Payments will be authorized but will require manual validation before collection. Make sure to capture transactions within the allotted time to avoid authorization expiration.', 'adminpayxpertconfiguration'),
                        'col' => 3,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Display mode', 'adminpayxpertconfiguration'),
                        'name' => 'redirect_mode',
                        'required' => true,
                        'options' => [
                            'query' => [
                                ['id' => PayxpertConfiguration::REDIRECT_MODE_REDIRECT, 'name' => $this->l('Redirection', 'adminpayxpertconfiguration')],
                                ['id' => PayxpertConfiguration::REDIRECT_MODE_SEAMLESS, 'name' => $this->l('IFrame', 'adminpayxpertconfiguration')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'help' => $this->l('Select the redirect mode for payments.', 'adminpayxpertconfiguration'),
                    ],
                    // [
                    //     'type' => 'switch',
                    //     'label' => $this->l('Enable OneClick', 'adminpayxpertconfiguration'),
                    //     'name' => 'oneclick',
                    //     'is_bool' => true,
                    //     'required' => true,
                    //     'values' => [
                    //         [
                    //             'id' => 'one_click_on',
                    //             'value' => 1,
                    //         ],
                    //         [
                    //             'id' => 'one_click_off',
                    //             'value' => 0,
                    //         ],
                    //     ],
                    //     'help' => $this->l('Please note: By disabling this option, you will accept payments even if Liability Shift is not active. This means you assume full responsibility for any potential fraud and chargebacks.', 'adminpayxpertconfiguration'),
                    //     'alert_message' => $this->l('By disabling this option, you assume full responsibility for fraud and chargebacks.', 'adminpayxpertconfiguration'),
                    // ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable PayByLink', 'adminpayxpertconfiguration'),
                        'name' => 'paybylink',
                        'is_bool' => true,
                        'required' => true,
                        'values' => [
                            [
                                'id' => 'paybylink_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'paybylink_off',
                                'value' => 0,
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save', 'adminpayxpertconfiguration'),
                ],
            ];

            // Instalment Payment Parameters
            $this->fields_form[3]['form'] = [
                'legend' => [
                    'title' => $this->l('Instalment payment parameters', 'adminpayxpertconfiguration'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Activate logo', 'adminpayxpertconfiguration'),
                        'name' => 'instalment_logo_active',
                        'is_bool' => true,
                        'required' => true,
                        'values' => [
                            [
                                'id' => 'instalment_logo_active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'instalment_logo_active_off',
                                'value' => 0,
                            ],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Minimum amount for installment payment', 'adminpayxpertconfiguration'),
                        'name' => 'instalment_payment_min_amount',
                        'required' => true,
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Installment transactions are automatically processed monthly by the platform. The status of these transactions is updated in the Orders tab.', 'adminpayxpertconfiguration'),
                        'validation' => 'isUnsignedInt',
                        'hint' => $this->l('Only positive values are allowed.', 'adminpayxpertconfiguration'),
                        'attr' => [
                            'min' => 0,
                            'step' => 1,
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Instalment x2', 'adminpayxpertconfiguration'),
                        'name' => 'instalment_x2',
                        'required' => false,
                        'hint' => $this->l('Indicate the proportion to be paid in the first monthly payment. The remainder will be distributed equally between the other installments.', 'adminpayxpertconfiguration'),
                        'suffix' => '%',
                        'col' => 2,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Instalment x3', 'adminpayxpertconfiguration'),
                        'name' => 'instalment_x3',
                        'required' => false,
                        'hint' => $this->l('Indicate the proportion to be paid in the first monthly payment. The remainder will be distributed equally between the other installments.', 'adminpayxpertconfiguration'),
                        'suffix' => '%',
                        'col' => 2,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Instalment x4', 'adminpayxpertconfiguration'),
                        'name' => 'instalment_x4',
                        'required' => false,
                        'hint' => $this->l('Indicate the proportion to be paid in the first monthly payment. The remainder will be distributed equally between the other installments.', 'adminpayxpertconfiguration'),
                        'suffix' => '%',
                        'col' => 2,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save', 'adminpayxpertconfiguration'),
                ],
            ];

            // Customization
            $this->fields_form[4]['form'] = [
                'legend' => [
                    'title' => $this->l('Customization', 'adminpayxpertconfiguration'),
                    'icon' => 'icon-cogs',
                ],
                'input' => $paymentMethodsLangInputs,
                'submit' => [
                    'title' => $this->l('Save', 'adminpayxpertconfiguration'),
                ],
            ];

            // Notifications
            $this->fields_form[5]['form'] = [
                'legend' => [
                    'title' => $this->l('Notifications', 'adminpayxpertconfiguration'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Activate merchant notification', 'adminpayxpertconfiguration'),
                        'name' => 'notification_active',
                        'is_bool' => true,
                        'required' => true,
                        'values' => [
                            [
                                'id' => 'notification_active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'notification_active_off',
                                'value' => 0,
                            ],
                        ],
                        'attr' => ['data-toggle' => 'notification'],
                        'hint' => $this->l('Enable to receive notifications related to payment requests.', 'adminpayxpertconfiguration')
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Notification recipient', 'adminpayxpertconfiguration'),
                        'name' => 'notification_to',
                        'required' => false,
                        'desc' => $this->l('Enter the email address that will receive notifications.', 'adminpayxpertconfiguration'),
                        'attr' => ['data-dependent' => 'notification'],
                        'col' => 3,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Notification language', 'adminpayxpertconfiguration'),
                        'name' => 'notification_language',
                        'required' => true,
                        'options' => [
                            'query' => [
                                ['id' => 'en', 'name' => $this->l('English', 'adminpayxpertconfiguration')],
                                ['id' => 'fr', 'name' => $this->l('French', 'adminpayxpertconfiguration')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                        'attr' => ['data-dependent' => 'notification'],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save', 'adminpayxpertconfiguration'),
                ],
            ];

            // Field Values
            $configurationPaymentMethods = PayxpertConfigurationPaymentMethod::getAll($this->id_object);
            foreach ($configurationPaymentMethods as $configurationPaymentMethod) {
                $this->fields_value['payment_method_' . $configurationPaymentMethod['payment_method_id']] = $configurationPaymentMethod['active'];
            }
        }

        $this->context->smarty->assign([
            'downloadUrl' => $this->context->link->getAdminLink('AdminPayxpertConfiguration', true, [], ['action' => 'downloadLog']),
            'moduleDebugInfo' => $this->module->getModuleDebugInfo(),
        ]);

        return parent::renderForm();
    }

    public function beforeAdd($object)
    {
        /* @phpstan-ignore-next-line */
        $object->id_shop = (Shop::CONTEXT_ALL === Shop::getContext() ? 0 : $this->context->shop->id);

        return true;
    }

    public function processUpdate()
    {
        // input password auto-set if not modified
        if (!Tools::getValue('private_api_key')) {
            $configuration = PayxpertConfiguration::getCurrentObject();
            $_POST['private_api_key'] = $configuration->private_api_key;
        }

        parent::processUpdate();
    }

    // Custom verification
    public function _childValidation()
    {
        // Verify API keys
        $public_api_key = Tools::getValue('public_api_key');
        $private_api_key = Tools::getValue('private_api_key');

        $moduleInfo = $this->module->getModuleDebugInfo();

        if (!$moduleInfo['{is_key_valid}'] || $private_api_key) {
            $payxpertAccountInfo = Webservice::getAccountInfo($public_api_key, $private_api_key);

            if (isset($payxpertAccountInfo['error'])) {
                Logger::critical($payxpertAccountInfo['error']);
                $this->errors[] = $this->l('API keys not valid.', 'adminpayxpertconfiguration');
            }
        }
    }

    public function afterAdd($object)
    {
        try {
            /* @phpstan-ignore-next-line */
            $languages = Language::getLanguages(true, $object->id_shop);

            foreach ($this->paymentMethods as $paymentMethod) {
                $config = json_decode($paymentMethod['config'], true);

                // Toggle status
                Db::getInstance()->insert(
                    PayxpertConfigurationPaymentMethod::$definition['table'],
                    [
                        'configuration_id' => $object->id,
                        'payment_method_id' => $paymentMethod['id_payxpert_payment_method'],
                        'active' => 0,
                    ]
                );

                foreach ($languages as $language) {
                    // Customization label
                    Db::getInstance()->insert(
                        PayxpertPaymentMethodLang::$definition['table'],
                        [
                            'id_configuration' => $object->id,
                            'id_lang' => (int) $language['id_lang'],
                            'payment_method_id' => $paymentMethod['id_payxpert_payment_method'],
                            'name' => 'fr' === $language['iso_code'] ?
                                (isset($config['default_payment_label']['fr']) ? $config['default_payment_label']['fr'] : $config['default_payment_label']['en'])
                                : $config['default_payment_label']['en'],
                        ]
                    );
                }
            }
        } catch (Exception $e) {
            Logger::critical($e->getMessage());
            $this->errors[] = $e->getMessage();

            return false;
        }

        return true;
    }

    public function afterUpdate($object)
    {
        try {
            /* @phpstan-ignore-next-line */
            $languageIDs = Language::getLanguages(true, $object->id_shop, true);

            foreach ($this->paymentMethods as $paymentMethod) {
                Db::getInstance()->update(
                    PayxpertConfigurationPaymentMethod::$definition['table'],
                    [
                        'active' => Tools::getValue('payment_method_' . $paymentMethod['id_payxpert_payment_method']),
                    ],
                    'configuration_id = ' . (int) $object->id . ' AND payment_method_id = ' . (int) $paymentMethod['id_payxpert_payment_method']
                );

                foreach ($languageIDs as $languageID) {
                    Db::getInstance()->update(
                        PayxpertPaymentMethodLang::$definition['table'],
                        [
                            'name' => Tools::getValue('payment_method_lang_' . (int) $paymentMethod['id_payxpert_payment_method'] . '_' . $languageID),
                        ],
                        'id_configuration = ' . (int) $object->id . ' AND payment_method_id = ' . (int) $paymentMethod['id_payxpert_payment_method'] . ' AND id_lang = ' . (int) $languageID
                    );
                }
            }
        } catch (Exception $e) {
            Logger::critical($e->getMessage());
            $this->errors[] = $e->getMessage();

            return false;
        }

        return true;
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        /* Page CSS+JS */
        $this->addCSS($this->module->getPathUri() . 'views/css/admin/configuration/index.css');
        $this->addJS($this->module->getPathUri() . 'views/js/admin/configuration/index.js');

        /* Modal CSS+JS */
        $this->addCSS($this->module->getPathUri() . 'views/css/modal/modal_support.css');
        $this->addJS($this->module->getPathUri() . 'views/js/modal/modal_support.js');
    }

    public function initModal()
    {
        parent::initModal();

        $helper = new HelperForm();

        $helper->fields_value['lastname'] = '';
        $helper->fields_value['firstname'] = '';
        $helper->fields_value['email'] = '';
        $helper->fields_value['subject'] = '';

        $helper->currentIndex = AdminController::$currentIndex;
        $helper->token = Tools::getAdminTokenLite('AdminPayxpertConfiguration');

        $helper->show_cancel_button = false;

        $fields_form = [
            'form' => [
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Lastname'),
                        'name' => 'lastname',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Firstname'),
                        'name' => 'firstname',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => 'Email',
                        'name' => 'email',
                        'required' => true,
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('Subject'),
                        'name' => 'subject',
                        'required' => true,
                    ],
                ],
            ],
        ];

        $form = $helper->generateForm([$fields_form]);

        $this->context->smarty->assign([
            'support_form' => $form,
            'module_dir' => _MODULE_DIR_ . 'payxpert/',
        ]);

        $modal_content = $this->context->smarty->fetch($this->module->getLocalPath() . '/views/templates/modal/modal_support.tpl');

        $this->modals[] = [
            'modal_id' => 'payxpert_modal_support',
            'modal_class' => 'modal-md',
            'modal_title' => $this->l('Contact Us', 'adminpayxpertconfiguration'),
            'modal_content' => $modal_content,
            'modal_actions' => [
                [
                    'type' => 'button',
                    'value' => null,
                    'class' => 'btn-primary js-submit-support-demand',
                    'label' => $this->l('Send', 'adminpayxpertconfiguration'),
                ],
            ],
        ];
    }

    public function ajaxProcessSupportFormSubmit()
    {
        header('Content-Type: application/json');

        if (!Tools::getIsset('token') || Tools::getValue('token') != Tools::getAdminTokenLite('AdminPayxpertConfiguration')) {
            exit(json_encode(['error' => 'Invalid token']));
        }

        $lastname = Tools::getValue('lastname');
        $firstname = Tools::getValue('firstname');
        $email = Tools::getValue('email');
        $subject = Tools::getValue('subject');

        if (empty($lastname) || empty($firstname) || empty($email) || empty($subject)) {
            exit(json_encode(['error' => 'All fields are required']));
        }

        $shopName = Configuration::get('PS_SHOP_NAME');
        $moduleInfo = $this->module->getModuleDebugInfo();
        $configuration = PayxpertConfiguration::getCurrentObject();

        $templateVars = [
            '{lastname}' => $lastname,
            '{firstname}' => $firstname,
            '{email}' => $email,
            '{subject}' => $subject,
            '{shop_name}' => $shopName,
            '{mid}' => Validate::isLoadedObject($configuration) ? $configuration->public_api_key : 'NO ACCOUNT CONFIGURED YET',
        ];

        $templateVars = array_merge($templateVars, $moduleInfo);
        $templateVars = array_map(function($v) {
            return is_bool($v) ? ($v ? $this->l('yes') : $this->l('no')) : $v;
        }, $templateVars);

        $language = Context::getContext()->language;
        $subject = 'Demande SAV du marchand : ' . $shopName;

        $mailSent = Mail::Send(
            (int) $language->id,
            'ask_for_support_mail',
            $subject,
            $templateVars,
            'assistance@payxpert.com',
            null,
            $email,
            $lastname . ' ' . $firstname,
            null,
            null,
            _PS_MODULE_DIR_ . 'payxpert/mails/'
        );

        if (!$mailSent) {
            Logger::info('Support request submittion failed');

            exit(json_encode([
                'error' => $this->l('Failed to send the support request email. Please try again.', 'adminpayxpertconfiguration'),
            ]));
        }

        Logger::info('Support request submitted');

        exit(json_encode(['success' => $this->l('Form submitted successfully', 'adminpayxpertconfiguration')]));
    }

    public function processDownloadLog()
    {
        $logFile = Logger::getLogFilePath();

        if (!file_exists($logFile)) {
            exit($this->l('Log file not found.', 'adminpayxpertconfiguration'));
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="log_file_' . date('Y_m_d_His') . '.log"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($logFile));
        ob_clean();

        flush();
        readfile($logFile);

        exit;
    }
}
