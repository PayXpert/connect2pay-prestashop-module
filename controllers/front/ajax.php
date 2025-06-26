<?php

use Payxpert\Classes\PayxpertPaymentTransaction;

class PayxpertAjaxModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        header('Content-Type: application/json');
        $transactionID = Tools::getValue('transactionID');
        $paymentID = Tools::getValue('paymentID');

        if (!$transactionID || !$paymentID) {
            exit(json_encode(['success' => false, 'message' => 'Missing parameters']));
        }

        $orderID = PayxpertPaymentTransaction::getOrderIdByTransactionIdAndPaymentId($transactionID, $paymentID);

        if (!$orderID) {
            exit(json_encode(['success' => false, 'message' => 'Transaction not found']));
        }

        $order = new Order((int) $orderID);

        if (!Validate::isLoadedObject($order) || $order->id_customer != $this->context->customer->id) {
            exit(json_encode(['success' => false, 'message' => 'Unauthorized order']));
        }

        if (_PS_OS_PAYMENT_ == $order->current_state || $order->current_state == Configuration::get('OS_PAYXPERT_WAITING_CAPTURE')) {
            $urlRedirect = $this->context->link->getPageLink(
                'order-confirmation',
                true,
                (int) $this->context->customer->id_lang,
                [
                    'id_cart' => (int) $order->id_cart,
                    'id_module' => (int) $this->module->id,
                    'id_order' => (int) $order->id,
                    'key' => $this->context->customer->secure_key,
                ]
            );
        } else {
            // Order history detail
            $urlRedirect = $this->context->link->getPageLink(
                'order-detail',
                true,
                (int) $this->context->customer->id_lang,
                [
                    'id_order' => (int) $order->id,
                ]
            );
        }

        // Répondre avec succès
        exit(json_encode(['success' => true, 'urlRedirect' => $urlRedirect]));
    }
}
