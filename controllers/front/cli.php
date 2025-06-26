<?php

use Payxpert\Utils\CronService;
use Symfony\Component\Console\Output\ConsoleOutput;

class PayxpertCliModuleFrontController extends ModuleFrontController
{
    public $output;

    public function initContent()
    {
        parent::initContent();
        $context = \Context::getContext();

        // Autorisé uniquement en CLI ou en BO (employé connecté)
        if (php_sapi_name() !== 'cli') {
            header('HTTP/1.1 403 Forbidden');
            exit('Access denied.');
        }

        $this->ajax = true;
        $this->auth = false;
        $this->output = new ConsoleOutput();
    }

    /**
     * Appelé lorsqu'on fait simplement run.php sans action
     */
    public function displayAjax()
    {
        // On peut lister les actions dispo
        $this->output->writeln('Available actions: synchronizeInstallment | manualCaptureReminder');
        exit(CronService::EXECUTE_SUCCESS);
    }

    /**
     * Cron Synchronize Installment
     */
    public function displayAjaxSynchronizeInstallment()
    {
        try {
            $service = new CronService();
            $result  = $service->synchronizeInstallments($this->output);
            // retourne le code 0 ou 1
            exit((int) $result['status']);
        } catch (\Exception $e) {
            $this->output->writeln('<error>'.$e->getMessage().'</error>');
            exit(CronService::EXECUTE_FAILURE);
        }
    }

    /**
     * Cron manual capture reminders
     */
    public function displayAjaxManualCaptureReminder()
    {
        try {
            $service = new CronService();
            $result  = $service->sendManualCaptureReminders($this->output);
            exit((int) $result['status']);
        } catch (\Exception $e) {
            $this->output->writeln('<error>'.$e->getMessage().'</error>');
            exit(CronService::EXECUTE_FAILURE);
        }
    }
}
