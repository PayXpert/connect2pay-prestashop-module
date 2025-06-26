<?php

use Payxpert\Classes\PayxpertPaymentTransaction;
use Payxpert\Classes\PayxpertSubscription;
use Payxpert\Utils\CronService;
use Payxpert\Utils\Logger;

class AdminPayxpertSubscriptionController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = PayxpertSubscription::$definition['table'];
        $this->className = PayxpertSubscription::class;
        $this->display = 'list';
        $this->bootstrap = true;
        $this->list_no_link = true;

        $this->_select .= '
            "" AS need_sync,
            t.order_id AS order_id,
            (
                SELECT COUNT(*) 
                FROM `' . _DB_PREFIX_ . PayxpertPaymentTransaction::$definition['table'] . '` t
                WHERE t.`subscription_id` = a.`subscription_id`
                AND t.`operation` = "sale"
            ) AS sale_transactions_count
        ';

        $this->_join = '
            LEFT JOIN ' . _DB_PREFIX_ . PayxpertPaymentTransaction::$definition['table'] . ' t ON (t.transaction_id = a.transaction_id)
        ';

        parent::__construct();

        $this->_defaultOrderWay = 'DESC';
        $this->fields_list = [
            'order_id' => [
                'title' => $this->l('Order ID'),
                'callback' => 'renderOrderLink',
                'orderby' => false,
                'search' => false,
                'align' => 'center',
            ],
            'subscription_id' => [
                'title' => $this->l('Subscription ID'),
                'align' => 'center',
            ],
            'subscription_type' => [
                'title' => $this->l('Subscription Type'),
                'callback' => 'renderSubscriptionType',
                'align' => 'center',
            ],
            'state' => [
                'title' => $this->l('State'),
                'callback' => 'renderState',
                'align' => 'center',
            ],
            'need_sync' => [
                'title' => $this->l('Need Synchro'),
                'callback' => 'renderNeedSync',
                'orderby' => false,
                'search' => false,
                'align' => 'center',
            ],
            'trial_amount' => [
                'title' => $this->l('Initial Amount'),
                'type' => 'price',
                'callback' => 'renderAmount',
                'currency' => true,
                'align' => 'text-right',
            ],
            'amount' => [
                'title' => $this->l('Installment Amount'),
                'type' => 'price',
                'callback' => 'renderAmount',
                'currency' => true,
                'align' => 'text-right',
            ],
            'period' => [
                'title' => $this->l('Period'),
                'callback' => 'renderHumanPeriod',
            ],
            'period_start' => [
                'title' => $this->l('Last payment'),
                'callback' => 'renderDateFromTimestamp',
            ],
            'period_end' => [
                'title' => $this->l('Next payment'),
                'callback' => 'renderDateFromTimestamp',
            ],
            'sale_transactions_count' => [
                'title' => $this->l('Sales Transactions'),
                'orderby' => false,
                'search' => false,
                'align' => 'center',
            ],
            'iterations_left' => [
                'title' => $this->l('Iterations Left'),
                'align' => 'center',
            ],
            'retries' => [
                'title' => $this->l('Retries'),
                'align' => 'center',
            ],
        ];
    }

    public function initToolbar()
    {
        parent::initToolbar();

        if (isset($this->toolbar_btn['new'])) {
            unset($this->toolbar_btn['new']);
        }
    }

    public static function renderSubscriptionType($type)
    {
        return $type === 'partpayment' ? 'Installment' : 'Subscription';
    }

    public static function renderAmount($amount)
    {
        return Tools::displayPrice($amount / 100);
    }

    public static function renderHumanPeriod($period)
    {
        try {
            $interval = new DateInterval($period);
            $parts = [];
            if ($interval->y) $parts[] = $interval->y . ' year(s)';
            if ($interval->m) $parts[] = $interval->m . ' month(s)';
            if ($interval->d) $parts[] = $interval->d . ' day(s)';
            if ($interval->h) $parts[] = $interval->h . ' hour(s)';
            if ($interval->i) $parts[] = $interval->i . ' minute(s)';
            if ($interval->s) $parts[] = $interval->s . ' second(s)';
            return implode(', ', $parts) ?: '—';
        } catch (Exception $e) {
            return $period;
        }
    }

    public static function renderDateFromTimestamp($timestamp)
    {
        if (!$timestamp) {
            return '—';
        }
        return date('Y-m-d H:i', (int)$timestamp);
    }

    public static function renderState($state)
    {
        switch ($state) {
            case 'active':
                return '<span class="badge badge-success">Active</span>';
            case 'finished':
                return '<span class="badge badge-info">Finished</span>';
            default:
                return '<span class="badge badge-danger">' . htmlspecialchars(ucfirst($state)) . '</span>';
        }
    }

    public static function renderNeedSync($value, $row)
    {
        if (empty($row['period_end'])) {
            return '';
        }

        try {
            $next = (int)$row['period_end'];
            $nextTS = (new DateTime("@$next"))->getTimestamp();
            return ($nextTS < time()) ? '<span title="Sync required">⚠️</span>' : '';
        } catch (Exception $e) {
            return '';
        }
    }

    public function renderOrderLink($orderId)
    {
        return '<a href="' . $this->context->link->getAdminLink('AdminOrders') . '&vieworder&id_order=' . (int) $orderId . '">' . $orderId . '</a>';
    }

    public function ajaxProcessSyncInstallment()
    {
        $svc = new CronService();
        $res = $svc->synchronizeInstallments();
        die(json_encode([
            'success'  => $res['status'] === 0,
            'message' => $this->l('An error occured')
        ]));
    }
}
