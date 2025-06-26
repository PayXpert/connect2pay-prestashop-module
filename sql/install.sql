CREATE TABLE IF NOT EXISTS _DB_PREFIX_payxpert_configuration (
    id_payxpert_configuration INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_shop INT NOT NULL,
    public_api_key VARCHAR(255) NOT NULL,
    private_api_key VARCHAR(255) NOT NULL,
    capture_mode INT DEFAULT 0,
    redirect_mode INT DEFAULT 0,
    date_add TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_upd TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    active BOOLEAN DEFAULT 0,
    notification_active BOOLEAN DEFAULT 0,
    notification_to VARCHAR(255) DEFAULT NULL,
    notification_language VARCHAR(3) DEFAULT NULL,
    amex BOOLEAN DEFAULT 0,
    oneclick BOOLEAN DEFAULT 0,
    paybylink BOOLEAN DEFAULT 0,
    capture_manual_email VARCHAR(255) DEFAULT NULL,
    instalment_payment_min_amount INT DEFAULT 0,
    instalment_x2 FLOAT DEFAULT 50.00,
    instalment_x3 FLOAT DEFAULT 33.34,
    instalment_x4 FLOAT DEFAULT 25.00,
    instalment_logo_active BOOLEAN DEFAULT 0,
    -- Indexes
    UNIQUE INDEX idx_shop (id_shop)
);

CREATE TABLE IF NOT EXISTS _DB_PREFIX_payxpert_payment_method (
    id_payxpert_payment_method INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    config JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS _DB_PREFIX_payxpert_payment_method_lang (
    id_payxpert_payment_method_lang INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_configuration INT UNSIGNED NOT NULL,
    id_lang INT NOT NULL,
    payment_method_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (id_configuration) REFERENCES _DB_PREFIX_payxpert_configuration(id_payxpert_configuration),
    FOREIGN KEY (id_lang) REFERENCES _DB_PREFIX_lang(id_lang),
    FOREIGN KEY (payment_method_id) REFERENCES _DB_PREFIX_payxpert_payment_method(id_payxpert_payment_method),
    -- Indexes
    INDEX idx_id_configuration (id_configuration),
    INDEX idx_id_lang (id_lang),
    INDEX idx_payment_method_id (payment_method_id)
);

CREATE TABLE IF NOT EXISTS _DB_PREFIX_payxpert_configuration_payment_method (
    id_payxpert_configuration_payment_method INT AUTO_INCREMENT,
    configuration_id INT UNSIGNED NOT NULL,
    payment_method_id INT UNSIGNED NOT NULL,
    active BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (id_payxpert_configuration_payment_method),
    FOREIGN KEY (configuration_id) REFERENCES _DB_PREFIX_payxpert_configuration(id_payxpert_configuration),
    FOREIGN KEY (payment_method_id) REFERENCES _DB_PREFIX_payxpert_payment_method(id_payxpert_payment_method)
);

CREATE TABLE IF NOT EXISTS _DB_PREFIX_payxpert_payment_transaction (
    id_payxpert_payment_transaction INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_shop INT NOT NULL,
    transaction_id VARCHAR(128) NOT NULL,
    transaction_referal_id VARCHAR(128),
    order_id INT UNSIGNED NOT NULL,
    payment_id VARCHAR(50) NOT NULL,
    liability_shift BOOLEAN DEFAULT FALSE,
    payment_method VARCHAR(50) NOT NULL,
    operation VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    result_code VARCHAR(10) NOT NULL,
    result_message TEXT NOT NULL,
    date_add DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    order_slip_id INT DEFAULT NULL,
    subscription_id INT DEFAULT NULL,
    -- Index pour accélérer les recherches
    UNIQUE INDEX idx_transaction_id (transaction_id),
    INDEX idx_order_id (order_id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_id_shop (id_shop),
    INDEX idx_subscription_id (subscription_id)
);

CREATE TABLE IF NOT EXISTS _DB_PREFIX_payxpert_payment_token (
    id_payxpert_payment_token INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_token VARCHAR(50) NOT NULL,
    customer_token VARCHAR(50) NOT NULL,
    date_add DATETIME NOT NULL,
    id_customer INT NOT NULL,
    id_cart INT NOT NULL,
    is_paybylink BOOLEAN DEFAULT 0,
    INDEX idx_customer_token (customer_token),
    INDEX idx_merchant_token (merchant_token),
    INDEX idx_is_paybylink (is_paybylink)
);

CREATE TABLE IF NOT EXISTS _DB_PREFIX_payxpert_subscription (
    `id_payxpert_subscription` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subscription_id` INT NOT NULL,
    `subscription_type` VARCHAR(32) NOT NULL,
    `offer_id` INT UNSIGNED DEFAULT 0,
    `transaction_id` VARCHAR(64) NOT NULL,
    `amount` INT NOT NULL,
    `period` VARCHAR(32) DEFAULT NULL,
    `trial_amount`INT DEFAULT NULL,
    `trial_period` VARCHAR(32) DEFAULT NULL,
    `state` VARCHAR(32) NOT NULL,
    `subscription_start` INT UNSIGNED DEFAULT 0,
    `period_start` INT UNSIGNED DEFAULT 0,
    `period_end` INT UNSIGNED DEFAULT 0,
    `cancel_date` INT UNSIGNED DEFAULT 0,
    `cancel_reason` TEXT DEFAULT NULL,
    `iterations` INT DEFAULT 0,
    `iterations_left` INT DEFAULT 0,
    `retries` INT DEFAULT 0,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_payxpert_subscription`),
    UNIQUE KEY `uniq_subscription_id` (`subscription_id`),
    FOREIGN KEY (`transaction_id`) REFERENCES _DB_PREFIX_payxpert_payment_transaction(transaction_id)
);

CREATE TABLE IF NOT EXISTS _DB_PREFIX_payxpert_cron_log (
  `id_payxpert_cron_log` int UNSIGNED NOT NULL,
  `cron_type` tinyint NOT NULL,
  `date_add` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `duration` float DEFAULT NULL,
  `status` tinyint DEFAULT '0',
  `context` text,
  `has_error` tinyint DEFAULT '0',
  INDEX idx_date_add (date_add),
  INDEX idx_cron_type (cron_type)
);

-- INSERTS

INSERT INTO _DB_PREFIX_payxpert_payment_method (name, config) VALUES
    ('Credit Card', '{"payment_method": "CreditCard", "logo_img": "credit_card.jpg", "default_payment_label": {"en" : "Payment via Credit Card by PayXpert", "fr": "Paiement simple via Carte de crédit par PayXpert"}}'),
    ('Credit Card x2', '{"instalment_configuration": "instalment_x2", "instalment_x_times": 2, "payment_method": "CreditCard", "payment_mode": "InstalmentsPayments", "logo_img": "credit_card.jpg", "default_payment_label": {"en" : "Pay in 2 installments with no fees with PayXpert", "fr": "Payez en 2 fois sans frais avec PayXpert"}}'),
    ('Credit Card x3', '{"instalment_configuration": "instalment_x3", "instalment_x_times": 3, "payment_method": "CreditCard", "payment_mode": "InstalmentsPayments", "logo_img": "credit_card.jpg", "default_payment_label": {"en" : "Pay in 3 installments with no fees with PayXpert", "fr": "Payez en 3 fois sans frais avec PayXpert"}}'),
    ('Credit Card x4', '{"instalment_configuration": "instalment_x4", "instalment_x_times": 4, "payment_method": "CreditCard", "payment_mode": "InstalmentsPayments", "logo_img": "credit_card.jpg", "default_payment_label": {"en" : "Pay in 4 installments with no fees with PayXpert", "fr": "Payez en 4 fois sans frais avec PayXpert"}}'),
    ('Alipay', '{"payment_method": "Alipay", "logo_img": "alipay.png", "default_payment_label": {"en" : "Payment via Alipay by PayXpert", "fr": "Paiement simple via AliPay par PayXpert"}}'),
    ('WeChat', '{"payment_method": "WeChat", "logo_img": "wechat.png", "default_payment_label": {"en" : "Payment via WeChat by PayXpert", "fr": "Paiement simple via WeChat par PayXpert"}}'),
    ('Applepay', '{"payment_method": "Applepay", "logo_img": "appleypay.png", "default_payment_label": {"en" : "Payment via Apple Pay by PayXpert", "fr": "Paiement simple via ApplePay par PayXpert"}, "allowed_front_office": false, "allowed_back_office": false}')
    -- ('Subscription', '{"payment_method": "Subscription", "default_payment_label": {"en" : "Subscription payment via Credit Card by PayXpert", "fr": "Paiement par abonnement via Carte de crédit par PayXpert"}}'),
    -- ('PayByLink', '{"payment_method": "PayByLink", "default_payment_label": {"en" : "Payment via PayByLink", "fr": "Paiement via PayByLink"}, "allowed_front_office": false}')
;
