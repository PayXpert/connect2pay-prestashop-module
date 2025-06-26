<?php

namespace Payxpert\Classes;

class PayxpertPaymentMethod extends \ObjectModel
{
    public $id_payxpert_payment_method;
    public $name;
    public $config;
    public $created_at;
    public $updated_at;

    public static $definition = [
        'table' => 'payxpert_payment_method',
        'primary' => 'id_payxpert_payment_method',
        'fields' => [
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
            'config' => ['type' => self::TYPE_HTML, 'validate' => 'isJson', 'required' => false],
            'created_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'required' => true],
            'updated_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'required' => true],
        ],
    ];

    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        parent::__construct($id, $id_lang, $id_shop);

        if (!$this->id) {
            $this->created_at = date('Y-m-d H:i:s');
            $this->updated_at = date('Y-m-d H:i:s');
        }
    }

    public function update($null_values = false)
    {
        $this->updated_at = date('Y-m-d H:i:s');

        return parent::update($null_values);
    }

    public function toArray()
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'config' => json_decode($this->config, true),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public static function getAll()
    {
        $dbQuery = new \DbQuery();
        $dbQuery
            ->select('*')
            ->from(self::$definition['table'])
        ;

        return \Db::getInstance()->executeS($dbQuery);
    }
}
