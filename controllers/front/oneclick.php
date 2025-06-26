<?php

class PayxpertOneclickModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $this->setTemplate('module:payxpert/views/templates/front/customer_oneclick.tpl');
    }

    public function process()
    {
        /* Assign var to smarty */
        $this->context->smarty->assign([
            'test' => 'test',
        ]);
    }

    public function postProcess()
    {
    }
}
