<?php

namespace Payxpert\Classes;

class PayxpertCronLog extends \ObjectModel
{
    const CRON_TYPE_INSTALLMENT = 0;
    const CRON_TYPE_REMINDER = 1;

    CONST STATUS_SUCCESS = 0;
    CONST STATUS_ERROR = 1;

    public $id_payxpert_cron_log;
    public $cron_type;
    public $date_add;
    public $duration;
    public $status;
    public $context;
    public $has_error;

    public static $definition = array(
        'table' => 'payxpert_cron_log',
        'primary' => 'id_payxpert_cron_log',
        'multilang' => false,
        'fields' => array(
            'cron_type' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true),
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
            'duration' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat'),
            'status' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'context' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'),
            'has_error' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
        ),
    );

    public static function getLast($limit = 5)
    {
        $dbQuery = new \DbQuery();
        $dbQuery
            ->select('*')
            ->from(self::$definition['table'])
            ->limit((int) $limit)
            ->orderBy('date_add DESC')
        ;

        return \Db::getInstance()->executeS($dbQuery);
    }
}
