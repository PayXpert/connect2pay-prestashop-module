<?php

namespace Payxpert\Classes;

class PayxpertSubscription extends \ObjectModel
{
    public $id_subscription_payment;
    public $subscription_id;
    public $subscription_type;
    public $offer_id;
    public $transaction_id;
    public $amount;
    public $period;
    public $trial_amount;
    public $trial_period;
    public $state;
    public $subscription_start;
    public $period_start;
    public $period_end;
    public $cancel_date;
    public $cancel_reason;
    public $iterations;
    public $iterations_left;
    public $retries;
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'payxpert_subscription',
        'primary' => 'id_payxpert_subscription',
        'multilang' => false,
        'fields' => array(
            'subscription_id' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 64),
            'subscription_type' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 32),
            'offer_id' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'transaction_id' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 64),
            'amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true),
            'period' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 32),
            'trial_amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isPrice'),
            'trial_period' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 32),
            'state' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 32),
            'subscription_start' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'period_start' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'period_end' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'cancel_date' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'cancel_reason' => array('type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'),
            'iterations' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'iterations_left' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'retries' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );

    public static function getBySubscriptionId($subscription_id)
    {
        $id = \Db::getInstance()->getValue('
            SELECT `' . static::$definition['primary'] . '`
            FROM `' . _DB_PREFIX_ . static::$definition['table'] . '`
            WHERE `subscription_id` = "' . pSQL($subscription_id) . '"
        ');

        return $id ? new static($id) : null;
    }

    public static function getNeedSynchronization()
    {
        return \Db::getInstance()->executeS('
            SELECT transaction_id, id_payxpert_subscription, subscription_id, id_payxpert_subscription
            FROM `' . _DB_PREFIX_ . static::$definition['table'] . '`
            WHERE UNIX_TIMESTAMP(NOW()) > period_end AND period_end != 0
        ');
    }
}
