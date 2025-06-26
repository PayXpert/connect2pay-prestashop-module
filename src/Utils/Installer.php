<?php

declare(strict_types=1);

namespace Payxpert\Utils;

use Payxpert\Exception\CurlException;
use PrestaShopBundle\Install\SqlLoader;

/**
 * Class Installer.
 */
class Installer
{
    /**
     * @return bool
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \Exception
     */
    public static function install($module)
    {
        self::checkTechnicalRequirements();
        self::installExceptionTranslation($module);
        self::installTabs($module);
        self::installDb($module);
        self::registerHooks($module);
        self::installOrderState($module);

        return true;
    }

    /**
     * @return void
     */
    private static function checkTechnicalRequirements()
    {
        // @formatter:off
        if (false == extension_loaded('curl')) {
            throw new CurlException();
        }
        // @formatter:on

        Logger::info('OK');
    }

    private static function installExceptionTranslation($module)
    {
        $module->l('Configuration not found.');
        $module->l('Handle failure.');
        $module->l('Hash check failure.');
        $module->l('Payment method not found.');
        $module->l('Payment token expired.');
        $module->l('You need to enable the cURL extension to use this module.');

        Logger::info('OK');
    }

    public static function installTabs($module)
    {
        $moduleTabs = $module->getModuleTabs();

        foreach ($moduleTabs as $moduleTab) {
            if (\Tab::getIdFromClassName($moduleTab['class_name'])) {
                Logger::info($moduleTab['class_name'] . ' already installed');
                continue;
            }

            $tab = new \Tab();
            $tab->active = true;
            $tab->class_name = $moduleTab['class_name'];
            $tab->id_parent = (int) \Tab::getIdFromClassName($moduleTab['parent_class_name']);
            $tab->module = $module->name;

            $tab->name = [];
            foreach (\Language::getLanguages(false) as $lang) {
                $isoCode = $lang['iso_code'];
                $tabName = $moduleTab['name'][$isoCode] = isset($moduleTab['name'][$isoCode]) ? $moduleTab['name'][$isoCode] : $moduleTab['name']['en'];
                $tab->name[$lang['id_lang']] = $tabName;
            }

            if (!$tab->add()) {
                throw new \Exception($module->l('Cannot add menu : %menu%', ['%menu%' => $moduleTab['class_name']], 'Modules.PayXpert.Installer'));
            }
            Logger::info($moduleTab['class_name'] . ' OK');
        }

        Logger::info('OK');
    }

    /**
     * @return bool
     */
    public static function uninstall($module)
    {
        // Delete Configuration Vars

        // Delete Tabs
        $moduleTabs = \Tab::getCollectionFromModule($module->name);
        foreach ($moduleTabs as $moduleTab) {
            $moduleTab->delete();
        }

        \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'payxpert_payment_method_lang`');
        \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'payxpert_subscription`');
        \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'payxpert_payment_transaction`');
        \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'payxpert_configuration_payment_method`');
        \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'payxpert_payment_method`');
        \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'payxpert_configuration`');
        \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'payxpert_payment_token`');

        Logger::info('OK');

        return true;
    }

    public static function registerHooks($module)
    {
        $hooks = $module->hooks;

        foreach ($hooks as $hook) {
            if (!$module->registerHook($hook)) {
                throw new \Exception($hook);
            }
            Logger::info($hook);
        }

        Logger::info('OK');
    }

    /**
     * @return void
     */
    public static function installDb($module)
    {
        $sqlLoader = new SqlLoader();
        $sqlLoader->setMetaData([
            '_DB_PREFIX_' => _DB_PREFIX_,
        ]);
        $sqlLoader->parseFile($module->getLocalPath() . 'sql/install.sql');

        Logger::info('OK');
    }

    public static function installOrderState($module)
    {
        $orderStates = [
            [
                'name' => [
                    \LanguageCore::getIdByIso('en') => 'Waiting for payment by PayByLink (PayXpert)',
                    \LanguageCore::getIdByIso('fr') => 'En attente de paiement par PayByLink (PayXpert)',
                ],
                'template' => $module->name,
                'color' => '#3498db', // Blue
                'send_email' => false,
                'invoice' => false,
                'logable' => true,
                'shipped' => false,
                'paid' => false,
                'hidden' => false,
                'delivery' => false,
                'deleted' => false,
                'module_name' => $module->name,
                'configurationValue' => 'OS_PAYXPERT_WAITING_PAYBYLINK',
            ],
            [
                'name' => [
                    \LanguageCore::getIdByIso('en') => 'Waiting for payment capture (PayXpert)',
                    \LanguageCore::getIdByIso('fr') => 'En attente de capture du paiement (PayXpert)',
                ],
                'color' => '#3498db', // Blue
                'send_email' => false,
                'invoice' => false,
                'logable' => true,
                'shipped' => false,
                'paid' => false,
                'hidden' => false,
                'delivery' => false,
                'deleted' => false,
                'module_name' => $module->name,
                'configurationValue' => 'OS_PAYXPERT_WAITING_CAPTURE',
            ],
            [
                'name' => [
                    \LanguageCore::getIdByIso('en') => 'Instalment Payment (PayXpert)',
                    \LanguageCore::getIdByIso('fr') => 'Paiement en plusieurs fois (PayXpert)',
                ],
                'color' => '#3498db', // Blue
                'send_email' => false,
                'invoice' => true,
                'logable' => false,
                'shipped' => false,
                'paid' => false,
                'hidden' => false,
                'delivery' => false,
                'deleted' => false,
                'module_name' => $module->name,
                'configurationValue' => 'OS_PAYXPERT_INSTALMENT_PAYMENT',
            ],
        ];

        foreach ($orderStates as $stateData) {
            $moduleName = $module->name;

            $dbQuery = new \DbQuery();
            $dbQuery->select('osl.name')
                ->from('order_state', 'os')
                ->leftJoin('order_state_lang', 'osl', 'os.id_order_state = osl.id_order_state')
                ->where('os.module_name = "' . pSQL($moduleName) . '"')
            ;
            $payxpertOrderStates = \Db::getInstance()->executeS($dbQuery);

            if (
                $payxpertOrderStates
                && array_intersect(
                    array_values($stateData['name']),
                    array_column($payxpertOrderStates, 'name')
                )
            ) {
                continue;
            }

            $orderState = new \OrderState();
            $orderState->hydrate($stateData);
            if (!$orderState->add()) {
                throw new \Exception($module->l('Error when trying to add order state : %orderstate%', ['%orderstate%' => reset($stateData['name'])], 'Modules.PayXpert.Installer'));
            }

            \Configuration::updateGlobalValue($stateData['configurationValue'], $orderState->id);
        }

        Logger::info('OK');
    }
}
