<?php

namespace Payxpert\Classes;

use Shop;

class PayxpertConfiguration extends \ObjectModel
{
    const REDIRECT_MODE_REDIRECT = 0;
    const REDIRECT_MODE_SEAMLESS = 1;

    const REDIRECT_MODES = [
        self::REDIRECT_MODE_REDIRECT,
        self::REDIRECT_MODE_SEAMLESS,
    ];

    const CAPTURE_MODE_AUTOMATIC = 0;
    const CAPTURE_MODE_MANUAL = 1;

    const CAPTURE_MODES = [
        self::CAPTURE_MODE_AUTOMATIC,
        self::CAPTURE_MODE_MANUAL,
    ];

    public $id_shop;
    public $id_payxpert_configuration;
    public $public_api_key;
    public $private_api_key;
    public $capture_mode;
    public $redirect_mode;
    public $date_add;
    public $date_upd;
    public $active;
    public $notification_active;
    public $notification_to;
    public $notification_language;
    public $amex;
    public $oneclick;
    public $paybylink;
    public $capture_manual_email;
    public $instalment_payment_min_amount;
    public $instalment_x2;
    public $instalment_x3;
    public $instalment_x4;
    public $instalment_logo_active;

    public static $definition = [
        'table' => 'payxpert_configuration',
        'primary' => 'id_payxpert_configuration',
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'public_api_key' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255, 'required' => true],
            'private_api_key' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255, 'required' => true],
            'capture_mode' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'redirect_mode' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'notification_active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'notification_to' => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 255],
            'notification_language' => ['type' => self::TYPE_STRING, 'validate' => 'isLanguageIsoCode', 'size' => 3],
            'amex' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'oneclick' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'paybylink' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'capture_manual_email' => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 255],
            'instalment_payment_min_amount' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'instalment_x2' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'instalment_x3' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'instalment_x4' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'instalment_logo_active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
        ],
    ];

    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        parent::__construct($id, $id_lang, $id_shop);

        if (null === $this->id) {
            $this->capture_mode = 0;
            $this->redirect_mode = 0;
            $this->active = false;
            $this->notification_active = false;
            $this->notification_to = null;
            $this->notification_language = null;
            $this->amex = false;
            $this->oneclick = false;
            $this->paybylink = false;
            $this->capture_manual_email = null;
            $this->instalment_payment_min_amount = 0;
            $this->instalment_x2 = 50.00;
            $this->instalment_x3 = 33.34;
            $this->instalment_x4 = 25.00;
            $this->instalment_logo_active = false;
        }
    }

    public static function getCurrentObject($idShop = null)
    {
        if (null === $idShop) {
            if (\Shop::isFeatureActive() && \Shop::CONTEXT_ALL === \Shop::getContext()) {
                $idShop = 0;
            } else {
                $idShop = \Shop::getContextShopID();
            }
        }

        $query = new \DbQuery();
        $query
            ->select('id_payxpert_configuration')
            ->from('payxpert_configuration')
            ->where('id_shop = ' . (int) $idShop);

        $id = \Db::getInstance()->getValue($query);

        // Get global configuration if there is none for the current shop
        if (0 != $idShop && !$id) {
            return self::getCurrentObject(0);
        }

        return $id ? new PayxpertConfiguration($id) : null;
    }

    public function hasPaymentMethod($paymentMethodName, $isActive = true)
    {
        $dbQuery = new \DbQuery();
        $dbQuery
            ->select('cpm.payment_method_id')
            ->from(PayxpertConfigurationPaymentMethod::$definition['table'], 'cpm')
            ->innerJoin(PayxpertPaymentMethod::$definition['table'], 'pm', 'cpm.payment_method_id = pm.id_payxpert_payment_method')
            ->where('pm.name = "' . pSQL($paymentMethodName) . '"')
            ->where('cpm.configuration_id = ' . (int) $this->id_payxpert_configuration)
            ->where('cpm.active = ' . (int) $isActive);

        return \Db::getInstance()->getValue($dbQuery);
    }

    public function hasConfigurationPaymentMethodId(int $paymentMethodId, $isActive = true)
    {
        $dbQuery = new \DbQuery();
        $dbQuery
            ->select('cpm.payment_method_id')
            ->from(PayxpertConfigurationPaymentMethod::$definition['table'], 'cpm')
            ->where('cpm.payment_method_id = ' . (int) $paymentMethodId)
            ->where('cpm.configuration_id = ' . (int) $this->id_payxpert_configuration)
            ->where('cpm.active = ' . (int) $isActive);

        return \Db::getInstance()->getValue($dbQuery);
    }

    public function getConfigurationPaymentMethods()
    {
        $collection = new \PrestaShopCollection(PayxpertConfigurationPaymentMethod::class);
        $collection->where('configuration_id', '=', (int) $this->id_payxpert_configuration);

        return $collection->getResults();
    }

    public function getPaymentMethodsLang()
    {
        $collection = new \PrestaShopCollection(PayxpertPaymentMethodLang::class);
        $collection->where('id_configuration', '=', (int) $this->id_payxpert_configuration);

        return $collection->getResults();
    }

    public static function getAll()
    {
        $collection = new \PrestaShopCollection(self::class);
        return $collection->getResults();
    }
}
