<?php

declare(strict_types=1);

namespace Payxpert\Utils;

use Payxpert\Classes\PayxpertConfiguration;
use Payxpert\Classes\PayxpertPaymentToken;
use PayXpert\Connect2Pay\Connect2PayClient;
use PayXpert\Connect2Pay\containers\constant\OperationType;
use PayXpert\Connect2Pay\containers\constant\OrderShippingType;
use PayXpert\Connect2Pay\containers\constant\PaymentMethod;
use PayXpert\Connect2Pay\containers\constant\PaymentMode;
use PayXpert\Connect2Pay\containers\constant\SubscriptionType;
use PayXpert\Connect2Pay\containers\Order as c2pOrder;
use PayXpert\Connect2Pay\containers\request\ExportTransactionsRequest;
use PayXpert\Connect2Pay\containers\request\PaymentPrepareRequest;
use PayXpert\Connect2Pay\containers\response\PaymentPrepareResponse;
use PayXpert\Connect2Pay\containers\response\PaymentStatus;
use PayXpert\Connect2Pay\containers\response\TransactionAttempt;
use PayXpert\Connect2Pay\containers\Shipping;
use PayXpert\Connect2Pay\containers\Shopper;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class Webservice
{
    const API_URL = 'https://connect2.payxpert.com';
    const API_PAYXPERT_URL = 'https://api.payxpert.com';

    public static function preparePayment($configuration, string $paymentMethod, \Cart $cart, string $paymentMode, array $instalmentParameters = [], bool $isPayByLink = false)
    {
        // get all informations
        $customer = new \Customer((int) $cart->id_customer);
        $currency = new \Currency((int) $cart->id_currency);
        $carrier = new \Carrier((int) $cart->id_carrier);
        $addr_delivery = new \Address((int) $cart->id_address_delivery);
        $addr_invoice = new \Address((int) $cart->id_address_invoice);
        $invoice_state = new \State((int) $addr_invoice->id_state);
        $invoice_country = new \Country((int) $addr_invoice->id_country);
        $delivery_state = new \State((int) $addr_delivery->id_state);
        $delivery_country = new \Country((int) $addr_delivery->id_country);
        $invoice_phone = (!empty($addr_invoice->phone)) ? $addr_invoice->phone : $addr_invoice->phone_mobile;
        $delivery_phone = (!empty($addr_delivery->phone)) ? $addr_delivery->phone : $addr_delivery->phone_mobile;

        // Param Shopper
        $shopper = new Shopper();
        $shopper->setId($customer->id);
        $shopper->setFirstName(\Tools::substr($customer->firstname, 0, 35));
        $shopper->setLastName(\Tools::substr($customer->lastname, 0, 35));
        $shopper->setAddress1(\Tools::substr(trim($addr_invoice->address1), 0, 255));
        $shopper->setZipcode(\Tools::substr($addr_invoice->postcode, 0, 10));
        $shopper->setCity(\Tools::substr($addr_invoice->city, 0, 50));
        $shopper->setState(\Tools::substr($invoice_state->name, 0, 30));
        $shopper->setCountryCode($invoice_country->iso_code);
        $shopper->setHomePhonePrefix($invoice_country->call_prefix);
        $shopper->setHomePhone(\Tools::substr(trim($invoice_phone), 0, 20));
        $shopper->setEmail($customer->email);

        // Param Order
        $order = new c2pOrder();
        $order->setId(\Order::generateReference());
        $order->setShippingType(OrderShippingType::DIGITAL_GOODS);
        $order->setDescription('Order N°' . $order->getId());
        $order->setCartContent(self::formatProductsApi($cart));

        // Param Shipping
        $shipping = new Shipping();
        $shipping->setName(\Tools::substr($carrier->name, 0, 50));
        $shipping->setCompany(\Tools::substr($addr_delivery->company, 0, 128));
        $shipping->setAddress1(\Tools::substr(trim($addr_delivery->address1), 0, 255));
        $shipping->setAddress2(\Tools::substr(trim($addr_delivery->address2), 0, 255));
        $shipping->setZipcode(\Tools::substr($addr_delivery->postcode, 0, 10));
        $shipping->setCity(\Tools::substr($addr_delivery->city, 0, 50));
        $shipping->setState(\Tools::substr($delivery_state->name, 0, 30));
        $shipping->setCountryCode($delivery_country->iso_code);
        $shipping->setPhone(\Tools::substr(trim($delivery_phone), 0, 20));

        // Affect to prepare request
        $prepareRequest = new PaymentPrepareRequest();
        $prepareRequest->setShopper($shopper);
        $prepareRequest->setOrder($order);
        $prepareRequest->setShipping($shipping);

        // Param PrepareRequest
        $amount = (int) \Tools::ps_round($cart->getOrderTotal() * 100);
        $prepareRequest->setCurrency($currency->iso_code);
        $prepareRequest->setAmount($amount);
        $prepareRequest->setPaymentMethod($paymentMethod);

        // Only for BankTransfert
        // Check on https://developers.payxpert.com/payment-page/create-payment/#payment-methods-networks
        // if (isset($paymentNetwork)) {
        //     $prepareRequest->setPaymentNetwork($paymentNetwork);
        // }

        $customData = [];
        $prepareRequest->setPaymentMode($paymentMode);
        if (PaymentMode::INSTALMENTS === $paymentMode) {
            if (empty($instalmentParameters)) {
                return ['error' => 'Instalment Parameters is required with this payment mode'];
            }
            $prepareRequest->setSubscriptionType(SubscriptionType::PARTPAYMENT);

            list($instalmentFirstAmount, $rebillAmount) = Utils::calculateInstalmentAmounts($amount, (float) $instalmentParameters['firstPercentage'], $instalmentParameters['xTimes']);

            $prepareRequest->setAmount($instalmentFirstAmount);
            $prepareRequest->setRebillAmount($rebillAmount);
            $prepareRequest->setRebillMaxIteration($instalmentParameters['xTimes'] - 1);
            $prepareRequest->setRebillPeriod('P30D');
            $customData['status'] = \Configuration::getGlobalValue('OS_PAYXPERT_INSTALMENT_PAYMENT');
        }

        $crypto = ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\Crypto\\Hashing');
        $hashedSecureKey = $crypto->hash($customer->id . $customer->secure_key);
        // Redirect URL
        $prepareRequest->setCtrlRedirectURL(\Context::getContext()->link->getModuleLink('payxpert', 'redirect', ['secureToken' => $hashedSecureKey]));
        // IPN URL
        $prepareRequest->setCtrlCallbackURL(\Context::getContext()->link->getModuleLink('payxpert', 'callback', ['secureToken' => $hashedSecureKey]));

        $prepareRequest->setCtrlCustomData(http_build_query($customData));

        // Merchant email notification can be enabled
        if ($configuration->notification_active) {
            $prepareRequest->setMerchantNotification(true);
            $prepareRequest->setMerchantNotificationTo($configuration->notification_to);
            $prepareRequest->setMerchantNotificationLang($configuration->notification_language);
        } else {
            $prepareRequest->setMerchantNotification(false);
        }

        // Special parameters for CreditCard paymentMethod
        if (PaymentMethod::CREDIT_CARD === $paymentMethod) {
            // TODO 
            // if ($configuration->liability_shift) {
                $prepareRequest->setSecure3d(true);
            // }

            $prepareRequest->setOperation(
                PayxpertConfiguration::CAPTURE_MODE_MANUAL == $configuration->capture_mode ?
                OperationType::AUTHORIZE : OperationType::SALE
            );
        }

        // Special parameters for payByLink
        if ($isPayByLink) {
            $prepareRequest->setTimeOut('P30D');
        }

        // Init api
        $c2pClient = new Connect2PayClient(
            self::API_URL,
            $configuration->public_api_key,
            $configuration->private_api_key
        );
        // print_r($prepareRequest);exit;

        /** @var bool|PaymentPrepareResponse */
        $result = $c2pClient->preparePayment($prepareRequest);

        if (false == $result || '200' !== $result->getCode()) {
            return ['error' => $c2pClient->getClientErrorMessage()];
        }

        // Save PaymentToken to register transaction
        try {
            $paymentToken = new PayxpertPaymentToken();
            $paymentToken->merchant_token = $result->getMerchantToken();
            $paymentToken->customer_token = $result->getCustomerToken();
            $paymentToken->id_customer = $customer->id;
            $paymentToken->id_cart = $cart->id;
            $paymentToken->is_paybylink = $isPayByLink;
            $paymentToken->save();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }

        return [
            'code' => $result->getCode(),
            'message' => $result->getMessage(),
            'customerToken' => $result->getCustomerToken(),
            'merchantToken' => $result->getMerchantToken(),
            'redirectUrl' => $c2pClient->getCustomerRedirectURL($result),
            'paymentToken' => $paymentToken,
        ];
    }

    public static function captureTransaction(PayxpertConfiguration $configuration, string $transactionId, int $amount)
    {
        $c2pClient = new Connect2PayClient(
            self::API_URL,
            $configuration->public_api_key,
            $configuration->private_api_key
        );

        $status = $c2pClient->captureTransaction($transactionId, $amount);
        if (null == $status || null == $status->getCode()) {
            return ['error' => $c2pClient->getClientErrorMessage()];
        }

        return [
            'code' => $status->getCode(),
            'message' => $status->getMessage(),
            'transaction_id' => $status->getTransactionID(),
        ];
    }

    public static function refundTransaction(PayxpertConfiguration $configuration, string $transactionId, int $amount)
    {
        $c2pClient = new Connect2PayClient(
            self::API_URL,
            $configuration->public_api_key,
            $configuration->private_api_key
        );

        $status = $c2pClient->refundTransaction($transactionId, $amount);
        if (null == $status || null == $status->getCode()) {
            return ['error' => $c2pClient->getClientErrorMessage()];
        }

        return [
            'code' => $status->getCode(),
            'message' => $status->getMessage(),
            'transaction_id' => $status->getTransactionID(),
        ];
    }

    public static function getPaymentStatus(PayxpertConfiguration $configuration, string $merchantToken)
    {
        // Init api
        $c2pClient = new Connect2PayClient(
            self::API_URL,
            $configuration->public_api_key,
            $configuration->private_api_key
        );

        /** @var PaymentStatus */
        $status = $c2pClient->getPaymentStatus($merchantToken);
        if (null == $status || null == $status->getErrorCode()) {
            return ['error' => $c2pClient->getClientErrorMessage()];
        }

        $result = [
            'merchant_token' => $status->getMerchantToken(),
            'status' => $status->getStatus(),
            'error_code' => $status->getErrorCode(),
            'custom_data' => $status->getCtrlCustomData(),
            'transaction_number' => count($status->getTransactions()),
            'last_transaction' => null,
            'others_transactions' => null,
        ];

        $transactionsCount = count($status->getTransactions());
        if ($transactionsCount > 0) {
            $transaction = $status->getLastInitialTransactionAttempt();

            /* @phpstan-ignore-next-line */
            if ($transaction) {
                $shopper = $transaction->getShopper();
                $paymentMeanInfo = $transaction->getPaymentMeanInfo();

                $result['last_transaction'] = [
                    'transaction_id' => $transaction->getTransactionID(),
                    'payment_method' => $transaction->getPaymentMethod(),
                    'payment_network' => $transaction->getPaymentNetwork(),
                    'operation' => $transaction->getOperation(),
                    'amount100' => $transaction->getAmount(),
                    'amount' => $transaction->getAmount() / 100,
                    'currency' => $status->getCurrency(),
                    'result_code' => $transaction->getResultCode(),
                    'result_message' => $transaction->getResultMessage(),
                    'transaction_date' => $transaction->getDateAsDateTime() ? ($transaction->getDateAsDateTime())->format('Y-m-d H:i:s T') : null,
                    'subscription_id' => $transaction->getSubscriptionID(),
                    'payment_mean_info' => self::getFormatPaymentMeanInfo($transaction, $paymentMeanInfo),
                    'shopper' => self::getFormatShopperInfo($shopper),
                ];
            }

            if ($transactionsCount > 1) {
                foreach ($status->getTransactions() as $attempt) {
                    if ($attempt->getTransactionId() != $transaction->getTransactionId()) {
                        $result['others_transactions'][] = [
                            'transaction_id' => $attempt->getTransactionID(),
                            'attempt_date' => null == $attempt->getDateAsDateTime() ? null : ($attempt->getDateAsDateTime())->format('Y-m-d H:i:s T'),
                            'payment_method' => $attempt->getPaymentMethod(),
                            'operation' => $attempt->getOperation(),
                            'amount100' => $attempt->getAmount(),
                            'amount' => $attempt->getAmount() / 100,
                            'currency' => $status->getCurrency(),
                            'result_code' => $attempt->getResultCode(),
                            'result_message' => $attempt->getResultMessage(),
                        ];
                    }
                }
            }
        }

        return $result;
    }

    public static function getTransactionInfo(PayxpertConfiguration $configuration, string $transactionId)
    {
        // Init api
        $c2pClient = new Connect2PayClient(
            self::API_URL,
            $configuration->public_api_key,
            $configuration->private_api_key
        );

        /** @var TransactionAttempt|null $transaction */
        $transaction = $c2pClient->getTransactionInfo($transactionId);
        if (null == $transaction) {
            return ['error' => $c2pClient->getClientErrorMessage()];
        }

        return self::formatTransaction($transaction);
    }

    public static function getAccountInfo($publicKey, $privateKey)
    {
        $c2pClient = new Connect2PayClient(
            self::API_URL,
            $publicKey,
            $privateKey
        );

        $info = $c2pClient->getAccountInformation();

        if (null == $info) {
            return ['error' => $c2pClient->getClientErrorMessage()];
        }

        $paymentMethods = null;
        $accPaymentMethods = $info->getPaymentMethods();
        if ($accPaymentMethods) {
            $paymentMethods = [];
            foreach ($accPaymentMethods as $methodInfo) {
                $paymentMethods[] = [
                    'paymentNetwork' => $methodInfo->getPaymentNetwork(),
                    'currencies' => $methodInfo->getCurrencies(),
                    'defaultOperation' => $methodInfo->getDefaultOperation(),
                    'options' => null == $methodInfo->getOptions()
                        ? null
                        : array_map(function ($option) {
                            return ['name' => $option->getName(), 'value' => $option->getValue()];
                        }, $methodInfo->getOptions()),
                ];
            }
        }

        return [
            'name' => $info->getName(),
            'displayTerms' => $info->getDisplayTerms(),
            'termsUrl' => $info->getTermsUrl(),
            'supportUrl' => $info->getSupportUrl(),
            'maxAttempts' => $info->getMaxAttempts(),
            'notificationOnSuccess' => $info->getNotificationOnSuccess(),
            'notificationOnFailure' => $info->getNotificationOnFailure(),
            'notificationSenderName' => $info->getNotificationSenderName(),
            'notificationSenderEmail' => $info->getNotificationSenderEmail(),
            'merchantNotification' => $info->getMerchantNotification(),
            'merchantNotificationTo' => $info->getMerchantNotificationTo(),
            'merchantNotificationLang' => $info->getMerchantNotificationLang(),
            'paymentMethods' => $paymentMethods,
        ];
    }

    /**
     * @param int $start Use mktime to create unix timestamp
     * @param int $end Use mktime to create unix timestamp
     */
    public static function getTransactionList(PayxpertConfiguration $configuration, int $start, int $end)
    {
        // Init api
        $c2pClient = new Connect2PayClient(
            self::API_URL,
            $configuration->public_api_key,
            $configuration->private_api_key
        );

        $request = new ExportTransactionsRequest();
        $request->setStartTime($start);
        $request->setEndTime($end);

        $result = $c2pClient->exportTransactions($request);

        if (null == $result) {
            return ['error' => $c2pClient->getClientErrorMessage()];
        }

        $transactions = [];
        foreach ($result->getTransactions() as $transaction) {
            $transactions[] = self::formatTransaction($transaction);
        }

        return $transactions;
    }

    public static function getStatusSubscription(PayxpertConfiguration $configuration, int $subscriptionID)
    {
        $url = self::API_PAYXPERT_URL . '/subscription/' . $subscriptionID;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Pour obtenir la réponse
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $configuration->public_api_key . ":" . $configuration->private_api_key);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return ['error' => curl_error($ch)];
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if ($data['errorCode'] != '000') {
            return ['error' => $data['errorMessage']];
        }

        return $data;
    }

    public static function handleRedirect(PayxpertConfiguration $configuration, string $merchantToken, string $data)
    {
        $c2pClient = new Connect2PayClient(
            self::API_URL,
            $configuration->public_api_key,
            $configuration->private_api_key
        );

        if (!$c2pClient->handleRedirectStatus($data, $merchantToken)) {
            return false;
        }

        $status = $c2pClient->getStatus();

        return [
            'errorCode' => $status->getErrorCode(),
            'customData' => $status->getCtrlCustomData(),
        ];
    }

    public static function handleCallback(PayxpertConfiguration $configuration)
    {
        $c2pClient = new Connect2PayClient(
            self::API_URL,
            $configuration->public_api_key,
            $configuration->private_api_key
        );

        /* @phpstan-ignore-next-line */
        if (false == $c2pClient->handleCallbackStatus()) {
            return false;
        }

        $status = $c2pClient->getStatus();

        return [
            'errorCode' => $status->getErrorCode(),
            'errorMessage' => $status->getErrorMessage(),
            'customData' => $status->getCtrlCustomData(),
            'transaction' => $status->getLastTransactionAttempt(),
            'orderId' => $status->getOrderId(),
        ];
    }

    private static function getFormatPaymentMeanInfo(TransactionAttempt $transaction, $paymentMeanInfo = null)
    {
        $result = [];

        if (!$paymentMeanInfo) {
            return null;
        }

        switch ($transaction->getPaymentMethod()) {
            case PaymentMethod::CREDIT_CARD:
                $result['is_3D_secure'] = $paymentMeanInfo->getIs3DSecure();

                if (null !== $paymentMeanInfo->getCardNumber()) {
                    $result['card_holder_name'] = $paymentMeanInfo->getCardHolderName();
                    $result['card_number'] = $paymentMeanInfo->getCardNumber();
                    $result['card_expiration'] = $paymentMeanInfo->getCardExpireMonth() . '/' . $paymentMeanInfo->getCardExpireYear();
                    $result['card_brand'] = $paymentMeanInfo->getCardBrand();
                    $result['card_level'] = null;
                    $result['card_country_code'] = null;
                    $result['card_bank_name'] = null;

                    if (null !== $paymentMeanInfo->getCardLevel()) {
                        $result['card_level'] = $paymentMeanInfo->getCardLevel();
                        $result['card_country_code'] = $paymentMeanInfo->getIinCountry();
                        $result['card_bank_name'] = $paymentMeanInfo->getIinBankName();
                    }
                }

                break;
            case PaymentMethod::BANK_TRANSFER:
                $sender = $paymentMeanInfo->getSender();
                $recipient = $paymentMeanInfo->getRecipient();

                $result = [
                    'sender' => null == $sender ? null : [
                        'holder_name' => $sender->getHolderName(),
                        'bank_name' => $sender->getBankName(),
                        'iban' => $sender->getIban(),
                        'bic' => $sender->getBic(),
                        'country_code' => $sender->getCountryCode(),
                    ],
                    'recipient' => null == $recipient ? null : [
                        'holder_name' => $recipient->getHolderName(),
                        'bank_name' => $recipient->getBankName(),
                        'iban' => $recipient->getIban(),
                        'bic' => $recipient->getBic(),
                        'country_code' => $recipient->getCountryCode(),
                    ],
                ];

                break;
            case PaymentMethod::DIRECT_DEBIT:
                $result = ['account' => null];

                $account = $paymentMeanInfo->getBankAccount();
                if (null !== $account) {
                    $sepaMandate = $account->getSepaMandate();

                    $result['account'] = [
                        'statement_descriptor' => $paymentMeanInfo->getStatementDescriptor(),
                        'collected_at' => $paymentMeanInfo->getCollectedAtAsDateTime() ? ($paymentMeanInfo->getCollectedAtAsDateTime())->format('Y-m-d H:i:s T') : null,
                        'bank_account' => [
                            'holder_name' => $account->getHolderName(),
                            'bank_name' => $account->getBankName(),
                            'iban' => $account->getIban(),
                            'bic' => $account->getBic(),
                            'country_code' => $account->getCountryCode(),
                        ],
                        'sepa_mandate' => null == $sepaMandate ? null : [
                            'description' => $sepaMandate->getDescription(),
                            'status' => $sepaMandate->getStatus(),
                            'type' => $sepaMandate->getType(),
                            'scheme' => $sepaMandate->getScheme(),
                            'signature_type' => $sepaMandate->getSignatureType(),
                            'phone_number' => $sepaMandate->getPhoneNumber(),
                            'signed_at' => null == $sepaMandate->getSignedAtAsDateTime() ? null : $sepaMandate->getSignedAtAsDateTime()->format('Y-m-d H:i:s T'),
                            'created_at' => null == $sepaMandate->getCreatedAtAsDateTime() ? null : $sepaMandate->getCreatedAtAsDateTime()->format('Y-m-d H:i:s T'),
                            'last_used_at' => null == $sepaMandate->getLastUsedAtAsDateTime() ? null : $sepaMandate->getLastUsedAtAsDateTime()->format('Y-m-d H:i:s T'),
                            'download_url' => $sepaMandate->getDownloadUrl(),
                        ],
                    ];
                }

                break;
            case PaymentMethod::WECHAT:
            case PaymentMethod::ALIPAY:
                $result = [
                    'total_fee' => $paymentMeanInfo->getTotalFee(),
                    'exchange_rate' => $paymentMeanInfo->getExchangeRate(),
                ];

                break;
        }

        return $result;
    }

    private static function getFormatShopperInfo($shopper = null)
    {
        if (!$shopper) {
            return null;
        }

        return [
            'name' => $shopper->getFirstName(),
            'address1' => $shopper->getAddress1(),
            'zip_code' => $shopper->getZipcode(),
            'city' => $shopper->getCity(),
            'country_code' => $shopper->getCountryCode(),
            'email' => $shopper->getEmail(),
            'birth_date' => $shopper->getBirthDate(),
            'id_number' => $shopper->getIdNumber(),
            'ip_address' => $shopper->getIpAddress(),
        ];
    }

    public static function getContainer()
    {
        $container = SymfonyContainer::getInstance();

        if (null === $container) {
            $kernel = new \AppKernel('prod', false);
            $kernel->boot();
            $container = $kernel->getContainer();
            $container->get('logger'); // Force intialisation of all services
        }

        return $container;
    }

    /**
     * Return array of product to fill Api Product properties.
     *
     * @return array
     */
    private static function formatProductsApi(\Cart $cart)
    {
        $products = [];

        foreach ($cart->getProducts() as $product) {
            $obj = new \Product((int) $product['id_product']);
            $products[] = [
                'CartProductId' => $product['id_product'],
                'CartProductName' => $product['name'],
                'CartProductUnitPrice' => $product['price'],
                'CartProductQuantity' => $product['quantity'],
                'CartProductBrand' => $obj->manufacturer_name,
                'CartProductMPN' => $product['ean13'],
                'CartProductCategoryName' => $product['category'],
                'CartProductCategoryID' => $product['id_category_default'],
            ];
        }

        return $products;
    }

    private static function formatTransaction($transaction)
    {
        $paymentMeanInfo = $transaction->getPaymentMeanInfo();
        $shopper = $transaction->getShopper();

        return [
            'payment_id' => $transaction->getPaymentID(),
            'payment_merchant_token' => $transaction->getPaymentMerchantToken(),
            'transaction_id' => $transaction->getTransactionID(),
            'ref_transaction_id' => $transaction->getRefTransactionID(),
            'provider_transaction_id' => $transaction->getProviderTransactionID(),
            'payment_method' => $transaction->getPaymentMethod(),
            'operation' => $transaction->getOperation(),
            'amount100' => $transaction->getAmount(),
            'amount' => $transaction->getAmount() / 100,
            'refunded_amount100' => $transaction->getRefundedAmount(),
            'refunded_amount' => $transaction->getRefundedAmount() / 100,
            'currency' => $transaction->getCurrency(),
            'result_code' => $transaction->getResultCode(),
            'result_message' => $transaction->getResultMessage(),
            'transaction_date' => null == $transaction->getDateAsDateTime() ? null : ($transaction->getDateAsDateTime())->format('Y-m-d H:i:s T'),
            'subscription_id' => $transaction->getSubscriptionID(),
            'payment_mean_info' => self::getFormatPaymentMeanInfo($transaction, $paymentMeanInfo),
            'shopper' => self::getFormatShopperInfo($shopper),
        ];
    }
}
