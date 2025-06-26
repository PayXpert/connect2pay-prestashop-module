<?php

use Payxpert\Classes\PayxpertPaymentTransaction;

class AdminPayxpertTransactionController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = PayxpertPaymentTransaction::$definition['table'];
        $this->className = PayxpertPaymentTransaction::class;
        $this->display = 'list';
        $this->bootstrap = true;
        $this->list_no_link = true;

        parent::__construct();

        $this->_defaultOrderWay = 'DESC';
        $this->fields_list = [
            'transaction_id' => [
                'title' => $this->l('Transaction ID', 'adminpayxperttransaction'),
                'type' => 'text',
                'align' => 'center',
            ],
            'transaction_referal_id' => [
                'title' => $this->l('Referal Transaction', 'adminpayxperttransaction'),
                'type' => 'text',
                'align' => 'center',
            ],
            'order_id' => [
                'title' => $this->l('Order ID', 'adminpayxperttransaction'),
                'type' => 'id_order',
                'align' => 'center',
                'callback' => 'getOrderLink',
            ],
            'liability_shift' => [
                'title' => $this->l('Liability Shift', 'adminpayxperttransaction'),
                'type' => 'text',
                'align' => 'center',
                'callback' => 'getLiabilityShiftIcon',
            ],
            'payment_method' => [
                'title' => $this->l('Payment Method', 'adminpayxperttransaction'),
                'type' => 'text',
                'align' => 'center',
            ],
            'operation' => [
                'title' => $this->l('Operation', 'adminpayxperttransaction'),
                'type' => 'text',
                'align' => 'center',
            ],
            'amount' => [
                'title' => $this->l('Amount', 'adminpayxperttransaction'),
                'type' => 'text',
                'align' => 'center',
                'callback' => 'formatAmount',
            ],
            'result_message' => [
                'title' => $this->l('Message', 'adminpayxperttransaction'),
                'type' => 'text',
                'callback' => 'formatMessage',
            ],
            'date_add' => [
                'title' => $this->l('Created At', 'adminpayxperttransaction'),
                'type' => 'datetime',
            ],
        ];
    }

    public function init()
    {
        parent::init();
    }

    public function initToolbar()
    {
        parent::initToolbar();

        if (isset($this->toolbar_btn['new'])) {
            unset($this->toolbar_btn['new']);
        }
    }

    public function getOrderLink($order_id)
    {
        $link = $this->context->link->getAdminLink('AdminOrders', true, [], ['id_order' => $order_id, 'vieworder' => 1]);

        return '<a href="' . $link . '">' . (int) $order_id . '</a>';
    }

    public function getLiabilityShiftIcon($liability_shift)
    {
        return $liability_shift ? '✔️' : '❌';
    }

    public function formatAmount($amount, $row) 
    {
        return $amount . ' ' . $row['currency'];
    }

    public function formatMessage($msg, $row) 
    {
        return $row['result_code'] . ' : ' . $msg;
    }
}
