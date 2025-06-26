<?php

declare(strict_types=1);

namespace Payxpert\Utils;

use OrderHistory;
use Payxpert\Classes\PayxpertCronLog;
use Payxpert\Classes\PayxpertPaymentTransaction;
use Payxpert\Classes\PayxpertSubscription;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;

class Utils
{
    const CREDIT_CARD = 'Credit Card';
    const CREDIT_CARD_X2 = 'Credit Card x2';
    const CREDIT_CARD_X3 = 'Credit Card x3';
    const CREDIT_CARD_X4 = 'Credit Card x4';
    const ALIPAY = 'Alipay';
    const WECHAT = 'WeChat';
    const APPLEPAY = 'Applepay';

    public static function formatConfigurationPaymentMethodsLang($configurationPaymentMethodsLang)
    {
        $formattedConfigurationPaymentMethodsLang = [];

        foreach ($configurationPaymentMethodsLang as $configurationPaymentMethodLang) {
            $formattedConfigurationPaymentMethodsLang[$configurationPaymentMethodLang->payment_method_id][$configurationPaymentMethodLang->id_lang] = $configurationPaymentMethodLang->name;
        }

        return $formattedConfigurationPaymentMethodsLang;
    }

    public static function isPaymentMethodAvailableForCurrency(int $idCurrency, array $paymentMethodConfig): bool
    {
        if (isset($paymentMethodConfig['allowed_currencies']) && !in_array($idCurrency, $paymentMethodConfig['allowed_currencies'])) {
            return false;
        }

        return true;
    }

    public static function isPaymentMethodAvailableForCountry(int $idAddressInvoice, array $paymentMethodConfig): bool
    {
        $address = \Address::getCountryAndState($idAddressInvoice);
        $country = new \Country($address['id_country']);
        if (isset($paymentMethodConfig['allowed_countries']) && !in_array($country->iso_code, $paymentMethodConfig['allowed_countries'])) {
            return false;
        }

        return true;
    }

    public static function isPaymentMethodAvailableForFrontOffice(array $paymentMethodConfig): bool
    {
        if (isset($paymentMethodConfig['allowed_front_office']) && !((bool) $paymentMethodConfig['allowed_front_office'])) {
            return false;
        }

        return true;
    }

    public static function isPaymentMethodAvailableForBackOffice(array $paymentMethodConfig): bool
    {
        if (isset($paymentMethodConfig['allowed_back_office']) && !((bool) $paymentMethodConfig['allowed_back_office'])) {
            return false;
        }

        return true;
    }

    public static function buildInstalmentSchedule(float $amount, float $firstPercentage, int $xTimes, int $id_currency): array
    {
        $schedule = [];
        $date = new \DateTime();
        $priceFormatter = new PriceFormatter();
        $currency = \Currency::getCurrencyInstance($id_currency);
        if (!\Validate::isLoadedObject($currency)) {
            throw new \Exception('Currency not found for the given cart.');
        }

        list($instalmentFirstAmount, $rebillAmount) = Utils::calculateInstalmentAmounts((int) \Tools::ps_round($amount * 100), $firstPercentage, $xTimes);

        $schedule[] = [
            'amount' => $instalmentFirstAmount / 100,
            'amountFormatted' => $priceFormatter->format($instalmentFirstAmount / 100, $currency),
            'date' => \Tools::displayDate($date->format('Y-m-d H:i:s')),
        ];

        for ($i = 0; $i < $xTimes - 1; ++$i) {
            $date->modify('+30 days');

            $schedule[] = [
                'amount' => $rebillAmount / 100,
                'amountFormatted' => $priceFormatter->format($rebillAmount / 100, $currency),
                'date' => \Tools::displayDate($date->format('Y-m-d H:i:s')),
            ];
        }

        return $schedule;
    }

    public static function calculateInstalmentAmounts(int $amount, float $firstPercentage, int $xTimes): array
    {
        if ($xTimes < 1) {
            throw new \Exception('xTimes must be at least 1.');
        }

        $instalmentFirstAmount = \Tools::ps_round(($firstPercentage * $amount) / 100);
        $remainingInstalments = $xTimes - 1;
        $rebillAmount = $remainingInstalments > 0 ? \Tools::ps_round(($amount - $instalmentFirstAmount) / $remainingInstalments, 2) : 0;
        $totalCalculated = $instalmentFirstAmount + ($rebillAmount * $remainingInstalments);
        $difference = $amount - $totalCalculated;

        if (abs($difference) >= 1) {
            $instalmentFirstAmount += $difference;
        }

        return [
            intval($instalmentFirstAmount),
            intval($rebillAmount),
        ];
    }

    public static function getOrderTransactionsFormatted(array $transactions)
    {
        $refundable = [];
        $capturable = [];
        $captured = [];
        $refunded = [];
        $orderSlipUsed = [];

        foreach ($transactions as $transaction) {
            if (PayxpertPaymentTransaction::RESULT_CODE_SUCCESS !== $transaction['result_code']) {
                continue;
            }

            if (
                PayxpertPaymentTransaction::OPERATION_SALE == $transaction['operation']
                || PayxpertPaymentTransaction::OPERATION_CAPTURE == $transaction['operation']
            ) {
                $refundable[$transaction['transaction_id']] = $transaction;
            }

            if (PayxpertPaymentTransaction::OPERATION_AUTHORIZE === $transaction['operation']) {
                $capturable[$transaction['transaction_id']] = $transaction;
            }

            if (isset($transaction['transaction_referal_id'])) {
                if (PayxpertPaymentTransaction::OPERATION_CAPTURE == $transaction['operation']) {
                    $captured[$transaction['transaction_referal_id']] = $transaction;
                } elseif (PayxpertPaymentTransaction::OPERATION_REFUND == $transaction['operation']) {
                    $refunded[$transaction['transaction_referal_id']][] = $transaction;
                }
            }

            if (!is_null($transaction['order_slip_id'])) {
                $orderSlipUsed[] = $transaction['order_slip_id'];
            }
        }

        foreach ($refundable as $transactionID => &$refundableTransaction) {
            $refundableTransaction['refundable_amount'] = $refundableTransaction['amount'];

            if (isset($refunded[$transactionID])) {
                $refundedAmount = array_sum(array_column($refunded[$transactionID], 'amount'));
                $refundableTransaction['refundable_amount'] -= $refundedAmount;
            }

            if ($refundableTransaction['refundable_amount'] <= 0) {
                unset($refundable[$transactionID]);
            }
        }

        $capturable = array_diff_key($capturable, $captured);

        return [
            'refundable' => $refundable,
            'capturable' => $capturable,
            'refunded' => $refunded,
            'captured' => $captured,
            'order_slip_used' => $orderSlipUsed,
        ];
    }

    public static function generateOrderProductsHTML(\Order $order): string
    {
        $orderProducts = '';

        foreach ($order->getProducts() as $product) {
            $productName = $product['product_name'];  // Product name
            $productQuantity = $product['product_quantity'];  // Product quantity
            $productPrice = \Tools::displayPrice($product['product_price_wt']);  // Product price including tax

            // Add the product info to the list (you can customize the format)
            $orderProducts .= "<p>- $productName x$productQuantity — $productPrice</p>";
        }

        return $orderProducts;
    }

    public static function syncSubscription(array $subscription, array $firstTransaction)
    {
        $updated = false;

        foreach ($subscription['transactionList'] as $subscriptionTransaction) {
            if ($subscriptionTransaction['transactionID'] == $firstTransaction['transaction_id']) {
                continue;
            }

            $transactionExist = PayxpertPaymentTransaction::isExistByTransactionId($subscriptionTransaction['transactionID']);
            if (!$transactionExist) {
                $payxpertPaymentTransaction = new PayxpertPaymentTransaction();
                $payxpertPaymentTransaction->hydrate($firstTransaction);
                $payxpertPaymentTransaction->id = null;
                $payxpertPaymentTransaction->transaction_id = $subscriptionTransaction['transactionID'];
                $payxpertPaymentTransaction->transaction_referal_id = $subscriptionTransaction['referralID'];
                $payxpertPaymentTransaction->amount = (float) ($subscriptionTransaction['amount']/100);
                $payxpertPaymentTransaction->result_code = $subscriptionTransaction['errorCode'];
                $payxpertPaymentTransaction->result_message = $subscriptionTransaction['status'];
                $payxpertPaymentTransaction->date_add = date('Y-m-d H:i:s', $subscriptionTransaction['date']);
                $payxpertPaymentTransaction->save();
                $updated = true;
            }
        }

        if ($updated) {
            $payxpertSubscription = PayxpertSubscription::getBySubscriptionId($firstTransaction['subscription_id']);
            $subscriptionInfo = Utils::toSnakeCaseKeys($subscription['subscription']);
            $payxpertSubscription->hydrate($subscriptionInfo);
            $payxpertSubscription->update();

            // Update orderState to paid when all iterations have been done
            if ($subscriptionInfo['iterations_left'] == 0) {
                $history = new OrderHistory();
                $history->id_order = $firstTransaction['order_id'];
                $history->changeIdOrderState(_PS_OS_PAYMENT_, $firstTransaction['order_id']);
                $history->add();
            }
        }

        return $updated;
    }

    public static function toSnakeCaseKeys(array $array): array {
        $result = [];

        foreach ($array as $key => $value) {
            // Convert camelCase or PascalCase to snake_case
            $snakeKey = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key));
            $result[$snakeKey] = $value;
        }

        return $result;
    }

    public static function buildDashboardTaskExecutorHTML(array $LOGs, $module)
    {
        ob_start();
        ?>
        <table class="log-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="text-center"><?= $module->l('Status') ?></th>
                    <th class="text-center"><?= $module->l('Details') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($LOGs as $index => $log): ?>
                    <?php
                        $cronType = $log['cron_type'] == 1 ? 'Subscription' : 'Installment';
                        $duration = intval($log['duration']) . ' ms';
                        $hasError = $log['has_error'] ? '⚠️' : '';
                        $contextArray = json_decode(html_entity_decode($log['context']), true);
                        if (!is_array($contextArray)) {
                            $contextArray = [$log['context']];
                        }
                        $contextHTML = '<div class="context-badges">';
                        foreach ($contextArray as $item) {
                            $contextHTML .= '<span class="pxp-badge pxp-badge-'.$item['type'].'">' . htmlspecialchars($item['message']) . '</span>';
                        }
                        $contextHTML .= '</div>';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($log['date_add']) ?></td>
                        <td class="text-center">
                            <?= $log['status'] == PayxpertCronLog::STATUS_SUCCESS ? '✔️' : '❌' ?>
                        </td>
                        <td class="text-center">
                            <button class="details-button" onclick="toggleDetails('details-<?= $index ?>')">
                                <?= $hasError . $module->l('See more') ?>
                            </button>
                        </td>
                    </tr>
                    <tr id="details-<?= $index ?>" class="log-details">
                        <td colspan="3">
                            <strong><?= $module->l('Type') ?>:</strong> <?= $cronType ?><br>
                            <strong><?= $module->l('Duration') ?>:</strong> <?= $duration ?><br>
                            <strong><?= $module->l('Error') ?>:</strong> <?= $hasError ?><br>
                            <strong><?= $module->l('History') ?>:</strong> <?= $contextHTML ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }


}
