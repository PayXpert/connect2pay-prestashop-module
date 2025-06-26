<?php

namespace Payxpert\Classes;

class PayxpertConfigurationPaymentMethod extends \ObjectModel
{
    public $configuration_id;
    public $payment_method_id;
    public $active;

    public static $definition = [
        'table' => 'payxpert_configuration_payment_method',
        'primary' => 'id_payxpert_configuration_payment_method',
        'fields' => [
            'configuration_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'payment_method_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true],
        ],
    ];

    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        parent::__construct($id, $id_lang, $id_shop);
        if (null === $this->id) {
            $this->active = false;
        }
    }

    public static function getAll($id_payxpert_configuration)
    {
        $dbQuery = new \DbQuery();
        $dbQuery
            ->select('*')
            ->from(self::$definition['table'])
            ->where('configuration_id = ' . (int) $id_payxpert_configuration)
        ;

        return \Db::getInstance()->executeS($dbQuery);
    }
}
