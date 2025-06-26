<?php

namespace Payxpert\Classes;

class PayxpertPaymentTransaction extends \ObjectModel
{
    const OPERATION_SALE = 'sale';
    const OPERATION_AUTHORIZE = 'authorize';
    const OPERATION_REFUND = 'refund';
    const OPERATION_CAPTURE = 'capture';
    const RESULT_CODE_SUCCESS = '000';
    const RESULT_CODE_CANCEL = '-1';
    const RESULT_CODE_CALLBACK_CANCEL = '-2';
    const LIABILITY_SHIFT_OK = 1;

    public $id_shop;
    public $transaction_id;
    public $transaction_referal_id;
    public $order_id;
    public $payment_id;
    public $liability_shift;
    public $payment_method;
    public $operation;
    public $amount;
    public $currency;
    public $result_code;
    public $result_message;
    public $date_add;
    public $order_slip_id;
    public $subscription_id;

    /**
     * Définition de l'entité pour PrestaShop 1.6 / 1.7.
     */
    public static $definition = [
        'table' => 'payxpert_payment_transaction',
        'primary' => 'id_payxpert_payment_transaction',
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'transaction_id' => ['type' => self::TYPE_STRING, 'size' => 128, 'validate' => 'isGenericName', 'required' => true],
            'transaction_referal_id' => ['type' => self::TYPE_STRING, 'size' => 128, 'validate' => 'isGenericName', 'required' => false],
            'order_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'payment_id' => ['type' => self::TYPE_STRING, 'size' => 50, 'validate' => 'isGenericName', 'required' => true],
            'liability_shift' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => false],
            'payment_method' => ['type' => self::TYPE_STRING, 'size' => 50, 'validate' => 'isGenericName', 'required' => true],
            'operation' => ['type' => self::TYPE_STRING, 'size' => 50, 'validate' => 'isGenericName', 'required' => true],
            'amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true],
            'currency' => ['type' => self::TYPE_STRING, 'size' => 3, 'validate' => 'isGenericName', 'required' => true],
            'result_code' => ['type' => self::TYPE_STRING, 'size' => 10, 'validate' => 'isGenericName', 'required' => true],
            'result_message' => ['type' => self::TYPE_HTML, 'validate' => 'isString', 'required' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
            'order_slip_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => false],
            'subscription_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => false],
        ],
    ];

    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        parent::__construct($id, $id_lang, $id_shop);

        $this->liability_shift = false;
    }

    /**
     * Convertir l'entité en tableau pour l'exportation.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'id_shop' => $this->id_shop,
            'transaction_id' => $this->transaction_id,
            'transaction_referal_id' => $this->transaction_referal_id,
            'order_id' => $this->order_id,
            'payment_id' => $this->payment_id,
            'liability_shift' => $this->liability_shift,
            'payment_method' => $this->payment_method,
            'operation' => $this->operation,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'result_code' => $this->result_code,
            'result_message' => $this->result_message,
            'date_add' => $this->date_add,
            'order_slip_id' => $this->order_slip_id,
            'subscription_id' => $this->subscription_id,
        ];
    }

    public static function getAllByOrderId($orderID)
    {
        $dbQuery = new \DbQuery();
        $dbQuery
            ->select('*')
            ->from(self::$definition['table'])
            ->where('order_id = ' . (int) $orderID)
        ;

        return \Db::getInstance()->executeS($dbQuery);
    }

    public static function getByReferalTransactionIdAndResultCode($transactionID, $resultCode)
    {
        $dbQuery = new \DbQuery();
        $dbQuery
            ->select('*')
            ->from(self::$definition['table'])
            ->where('transaction_referal_id = ' . pSQL($transactionID))
            ->where('result_code = ' . pSQL($resultCode))
        ;

        return \Db::getInstance()->executeS($dbQuery);
    }

    public static function isExistByTransactionReferalId($transactionID): bool
    {
        $dbQuery = new \DbQuery();
        $dbQuery
            ->select('COUNT(*)')
            ->from(bqSQL(self::$definition['table']))
            ->where("transaction_referal_id = '" . pSQL($transactionID) . "'");

        $result = (int) \Db::getInstance()->getValue($dbQuery);

        return $result > 0;
    }

    public static function isExistByTransactionId($transactionID): bool
    {
        $dbQuery = new \DbQuery();
        $dbQuery
            ->select('COUNT(*)')
            ->from(bqSQL(self::$definition['table']))
            ->where("transaction_id = '" . pSQL($transactionID) . "'");

        $result = (int) \Db::getInstance()->getValue($dbQuery);

        return $result > 0;
    }

    public static function getOrderIdByTransactionIdAndPaymentId($transactionID, $paymentID)
    {
        $dbQuery = new \DbQuery();
        $dbQuery
            ->select('order_id')
            ->from(PayxpertPaymentTransaction::$definition['table'])
            ->where('transaction_id = "' . pSQL($transactionID) . '"')
            ->where('payment_id = "' . pSQL($paymentID) . '"')
        ;

        return \Db::getInstance()->getValue($dbQuery);
    }

    public static function getByTransactionId($transactionID)
    {
        $query = new \DbQuery();
        $query
            ->select(self::$definition['primary'])
            ->from(self::$definition['table'])
            ->where('transaction_id = ' . (int) $transactionID);
        $id = \Db::getInstance()->getValue($query);

        return $id ? new self($id) : null;
    }
}
