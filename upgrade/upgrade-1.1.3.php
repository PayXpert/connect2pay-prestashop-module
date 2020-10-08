<?php
/**
* 2015-2020 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://nethues.com
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@nethues.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://nethues.com for more information.
*
*  @author    PrestaShop SA <contact@nethues.com>
*  @copyright 2015-2020 PrestaShop SA
*  @license   http://nethues.com  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_3($module)
{
    if (version_compare(_PS_VERSION_, '1.6', '>=') && version_compare(_PS_VERSION_, '1.7', '<')) {
        if (!$module->installDB()) {
            $errorMessage = Tools::displayError($module->l('PayXpert update : Tables failed.'));
            $module->addLog($errorMessage, 3, '000002');

            return false;
        }
        if (!$module->registerHook('adminOrder')) {
            $errorMessage = Tools::displayError($module->l('PayXpert update : hooks failed.'));
            $module->addLog($errorMessage, 3, '000002');

            return false;
        }

        $moduleParameters = array();
        $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_BANK_TRANSFER_GIROPAY';
        $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_WECHAT';
        $moduleParameters[] = 'PAYXPERT_PAYMENT_TYPE_ALIPAY';
        $moduleParameters[] = 'PAYXPERT_IS_IFRAME';

        // Add configuration parameters
        foreach ($moduleParameters as $parameter) {
            if (!Configuration::updateValue($parameter, '')) {
                $errorMessage = Tools::displayError($module->l('PayXpert update : configuration failed.'));
                $module->addLog($errorMessage, 3, '000002');

                return false;
            }
        }
    }

    return true;
}
