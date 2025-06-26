<?php

if ('cli' !== php_sapi_name()) {
    exit("Access denied.\n");
}

$_SERVER['REQUEST_METHOD'] = 'POST';

$_POST['fc'] = 'module';
$_POST['module'] = 'payxpert';
$_POST['controller'] = 'cli';

require_once dirname(__FILE__) . '/../../index.php';
