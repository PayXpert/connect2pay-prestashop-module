<?php

namespace Payxpert\Utils;

use Configuration;
use DateTime;
use Db;
use Mail;
use Payxpert\Classes\PayxpertConfiguration;
use Payxpert\Classes\PayxpertCronLog;
use Payxpert\Classes\PayxpertPaymentTransaction;
use Payxpert\Classes\PayxpertSubscription;
use Payxpert\Utils\Utils;
use Payxpert\Utils\Webservice;
use Symfony\Component\Console\Output\OutputInterface;

class CronService
{
    const EXECUTE_SUCCESS = 0;
    const EXECUTE_FAILURE = 1;

    const TYPE_ERROR   = 'error';
    const TYPE_INFO    = 'info';
    const TYPE_COMMENT = 'comment';

    const INTERVAL_DAYS = [6, 28];
    const MAX_DAYS      = 30;

    /** @var array<int, PayxpertConfiguration> */
    private $configurationsById = [];

    /** @var bool */
    private $hasError = false;

    /** @var array<array{type:string,message:string}> */
    private $outputBuffer = [];

    /** @var int */
    private $cronType;

    public function __construct()
    {
        // charge toutes les configurations
        $all = PayxpertConfiguration::getAll();
        foreach ($all as $cfg) {
            $this->configurationsById[$cfg->id_shop] = $cfg;
        }
        if (empty($this->configurationsById)) {
            throw new \RuntimeException('The module is not configured.');
        }
    }

    /**
     * Synchronise les installments en attente.
     *
     * @param OutputInterface|null $output  Optionnel, pour logger en CLI.
     * @return array{status:int, messages:array, duration:float}
     */
    public function synchronizeInstallments(OutputInterface $output = null): array
    {
        $start = microtime(true);
        $this->cronType = PayxpertCronLog::CRON_TYPE_INSTALLMENT;

        $installments = PayxpertSubscription::getNeedSynchronization();
        $count = count($installments);
        if ($count === 0) {
            $this->writeln('All installment are already synchronized.', self::TYPE_INFO, $output);
            return $this->finalize($start, PayxpertCronLog::STATUS_SUCCESS);
        }

        $this->writeln("Beginning synchronization of {$count} installment(s)", self::TYPE_INFO, $output);

        foreach ($installments as $inst) {
            try {
                $tx = PayxpertPaymentTransaction::getByTransactionId($inst['transaction_id']);
                if (!$tx) {
                    $this->writeln(
                        "No initial transaction found for [installmentID={$inst['id_payxpert_subscription']}]", 
                        self::TYPE_ERROR,
                        $output
                    );
                    continue;
                }
                $cfg = $this->configurationsById[$tx->id_shop] 
                     ?? $this->configurationsById[0] 
                     ?? null;
                if (!$cfg) {
                    $this->writeln(
                        "No configuration found for [shopID={$tx->id_shop}]", 
                        self::TYPE_ERROR,
                        $output
                    );
                    continue;
                }
                $info = Webservice::getStatusSubscription($cfg, $inst['subscription_id']);
                Utils::syncSubscription($info, $tx->toArray());
            } catch (\Exception $e) {
                $this->writeln(
                    "Critical error for [ID={$inst['id_payxpert_subscription']}] : ".$e->getMessage(),
                    self::TYPE_ERROR,
                    $output
                );
            }
        }

        return $this->finalize($start, $this->hasError ? PayxpertCronLog::STATUS_ERROR : PayxpertCronLog::STATUS_SUCCESS);
    }

    /**
     * Envoie les reminders de capture manuelle.
     *
     * @param OutputInterface|null $output
     * @return array{status:int, messages:array, duration:float}
     */
    public function sendManualCaptureReminders(OutputInterface $output = null): array
    {
        $start = microtime(true);
        $this->cronType = PayxpertCronLog::CRON_TYPE_REMINDER;

        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $waitingState = Configuration::get('OS_PAYXPERT_WAITING_CAPTURE');
        if (!$waitingState) {
            $this->writeln('Invalid configuration for OS_PAYXPERT_WAITING_CAPTURE.', self::TYPE_ERROR, $output);
            return $this->finalize($start, PayxpertCronLog::STATUS_ERROR);
        }

        foreach (self::INTERVAL_DAYS as $days) {
            $expiry = (new DateTime())
                ->modify('+'.(self::MAX_DAYS - $days).' days')
                ->format('Y-m-d');

            $qb = (new \DbQuery())
                ->select('o.reference,o.id_shop,o.total_paid,s.name,c.iso_code')
                ->from('orders','o')
                ->leftJoin('shop','s','o.id_shop=s.id_shop')
                ->leftJoin('currency','c','c.id_currency=o.id_currency')
                ->where('o.current_state='.(int)$waitingState)
                ->where("DATE(o.date_add)=DATE_SUB(CURDATE(), INTERVAL {$days} DAY)");

            $rows = Db::getInstance()->executeS($qb);
            if (!$rows) {
                $this->writeln("No orders for {$days}-day reminder.", self::TYPE_INFO, $output);
                continue;
            }

            $grouped = [];
            foreach ($rows as $r) {
                $grouped[$r['id_shop']][] = $r;
            }

            foreach ($grouped as $shopId => $orders) {
                $cfg = $this->configurationsById[$shopId] ?? $this->configurationsById[0] ?? null;
                if (!$cfg) {
                    $this->writeln("No config for shopID {$shopId}.", self::TYPE_COMMENT, $output);
                    continue;
                }
                $email = $cfg->capture_manual_email;
                if (!\Validate::isEmail($email)) {
                    $this->writeln("Invalid email for shopID {$shopId}.", self::TYPE_COMMENT, $output);
                    continue;
                }

                $this->writeln(
                    "{$days}-day reminder will be sent to {$email} for shopID {$shopId}.", 
                    self::TYPE_INFO,
                    $output
                );

                // génère les tableaux HTML
                list($orderTable, $totalTable) = $this->buildReminderTables($orders);

                $sent = Mail::Send(
                    $idLang,
                    'manual_capture_reminder',
                    "{$days}-day reminder: Capture orders",
                    [
                        '{shop_name}' => $orders[0]['name'],
                        '{total_orders_amount_per_currency_table}' => $totalTable,
                        '{total_orders}' => count($orders),
                        '{order_table}' => $orderTable,
                        '{expiry_date}' => $expiry,
                    ],
                    $email
                );
                $this->writeln($sent ? 'Email sent.' : 'Failed to send email.', $sent ? self::TYPE_INFO : self::TYPE_ERROR, $output);
            }
        }

        return $this->finalize($start, $this->hasError ? PayxpertCronLog::STATUS_ERROR : PayxpertCronLog::STATUS_SUCCESS);
    }

    /**
     * @param array[] $orders
     * @return array{0:string,1:string}
     */
    private function buildReminderTables(array $orders): array
    {
        $totals = [];
        $rows   = $totalsRows = [];

        foreach ($orders as $o) {
            $sym   = htmlspecialchars($o['iso_code']);
            $amt   = number_format($o['total_paid'],2,',',' ');
            $totals[$sym] = ($totals[$sym] ?? 0) + $o['total_paid'];
            $rows[] = "<tr><td>{$o['reference']}</td><td>{$amt} {$sym}</td></tr>";
        }
        foreach ($totals as $sym => $total) {
            $fmt   = number_format($total,2,',',' ');
            $totalsRows[] = "<tr><td>{$fmt}</td><td>{$sym}</td></tr>";
        }

        $table1 = '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;"><thead><tr><th>Reference</th><th>Amount</th></tr></thead><tbody>'.implode('',$rows).'</tbody></table>';
        $table2 = '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;"><thead><tr><th>Amount</th><th>Currency</th></tr></thead><tbody>'.implode('',$totalsRows).'</tbody></table>';
        return [$table1, $table2];
    }

    private function writeln(string $msg, string $type, OutputInterface $output = null)
    {
        $this->outputBuffer[] = ['type' => $type, 'message' => $msg];
        if ($output) {
            $output->writeln("<{$type}>{$msg}</{$type}>");
        }
        if ($type === self::TYPE_ERROR) {
            $this->hasError = true;
        }
    }

    /**
     * Enregistre le log et retourne le résultat
     *
     * @param float $start
     * @param int   $status
     * @return array{status:int,messages:array,duration:float}
     */
    private function finalize(float $start, int $status): array
    {
        $duration = microtime(true) - $start;

        $log = new PayxpertCronLog();
        $log->cron_type = $this->cronType;
        $log->duration  = $duration;
        $log->status    = $status;
        $log->context   = json_encode($this->outputBuffer);
        $log->has_error = $this->hasError;
        $log->save();

        return [
            'status'   => $status,
            'messages' => $this->outputBuffer,
            'duration' => $duration,
        ];
    }
}
