<?php return array(
    'root' => array(
        'name' => 'prestashop/payxpert',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '7765d6994ef8774363f144206c855cf8579b5576',
        'type' => 'prestashop-module',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'payxpert/connect2pay' => array(
            'pretty_version' => '2.71.0',
            'version' => '2.71.0.0',
            'reference' => '581723f3891d9295549cb053e324078e96871d56',
            'type' => 'library',
            'install_path' => __DIR__ . '/../payxpert/connect2pay',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'prestashop/payxpert' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '7765d6994ef8774363f144206c855cf8579b5576',
            'type' => 'prestashop-module',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
