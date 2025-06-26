<?php

namespace Payxpert\Classes;

class PayxpertPaymentToken extends \ObjectModel
{
    public $merchant_token;
    public $customer_token;
    public $date_add;
    public $id_customer;
    public $id_cart;
    public $is_paybylink;

    public static $definition = [
        'table' => 'payxpert_payment_token',
        'primary' => 'id_payxpert_payment_token',
        'fields' => [
            'merchant_token' => ['type' => self::TYPE_STRING, 'size' => 50, 'validate' => 'isGenericName', 'required' => true],
            'customer_token' => ['type' => self::TYPE_STRING, 'size' => 50, 'validate' => 'isGenericName', 'required' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_cart' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'is_paybylink' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true],
        ],
    ];

    public function toArray()
    {
        return [
            'merchant_token' => $this->merchant_token,
            'customer_token' => $this->customer_token,
            'date_add' => $this->date_add,
            'id_customer' => (int) $this->id_customer,
            'id_cart' => (int) $this->id_cart,
            'is_paybylink' => (int) $this->is_paybylink,
        ];
    }

    public static function getByMerchantToken(string $merchantToken)
    {
        $dbQuery = new \DbQuery();
        $dbQuery->select('*')
                ->from(self::$definition['table'])
                ->where('merchant_token = "' . pSQL($merchantToken) . '"');

        $data = \Db::getInstance()->getRow($dbQuery);

        $object = new self();
        $object->hydrate($data);

        return $object;
    }

    public static function getByCustomerToken(string $customerToken)
    {
        $dbQuery = new \DbQuery();
        $dbQuery->select('*')
                ->from(self::$definition['table'])
                ->where('customer_token = "' . pSQL($customerToken) . '"');

        $data = \Db::getInstance()->getRow($dbQuery);
        if (!$data) {
            return null;
        }

        $object = new self();
        $object->hydrate($data);

        return $object;
    }

    public static function existsRecentPaybylinkForIdCart($idCart, $isPayByLink = false): bool
    {
        $dbQuery = new \DbQuery();
        $dbQuery
            ->select(self::$definition['primary'])
            ->from(self::$definition['table'])
            ->where('id_cart = ' . (int) $idCart)
            ->where('is_paybylink = ' . (int) $isPayByLink)
            ->where('date_add > DATE_SUB(NOW(), INTERVAL 30 DAY)')
        ;

        return (bool) \Db::getInstance()->getValue($dbQuery);
    }
}
