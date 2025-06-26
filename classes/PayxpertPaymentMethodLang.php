<?php

namespace Payxpert\Classes;

class PayxpertPaymentMethodLang extends \ObjectModel
{
    public $id_configuration;
    public $id_lang;
    public $payment_method_id;
    public $name;

    public static $definition = [
        'table' => 'payxpert_payment_method_lang',
        'primary' => 'id_payxpert_payment_method_lang',
        'fields' => [
            'id_configuration' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_lang' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'payment_method_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 100, 'required' => true],
        ],
    ];

    public function toArray()
    {
        return [
            'id_configuration' => (int) $this->id_configuration,
            'id_lang' => (int) $this->id_lang,
            'payment_method_id' => (int) $this->payment_method_id,
            'name' => $this->name,
        ];
    }

    public static function getAll($id_payxpert_configuration)
    {
        $dbQuery = new \DbQuery();
        $dbQuery
            ->select('*')
            ->from(self::$definition['table'])
            ->where('id_configuration = ' . (int) $id_payxpert_configuration)
        ;

        return \Db::getInstance()->executeS($dbQuery);
    }
}
