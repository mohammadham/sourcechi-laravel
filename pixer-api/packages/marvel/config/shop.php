<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin email configuration
    |--------------------------------------------------------------------------
    |
    | Set the admin email. This will be used to send email when user contact through contact page.
    |
    */
    'admin_email' => env('ADMIN_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Shop url configuration
    |--------------------------------------------------------------------------
    |
    | Shop url is used in order placed template to go to shop order page.
    |
    */
    'shop_url' => env('SHOP_URL'),

    'dashboard_url' => env('DASHBOARD_URL'),

    'media_disk' => env('MEDIA_DISK'),

    'version' => env('APP_VERSION', 12),

    'stripe_api_key' => env('STRIPE_API_KEY'),

    'app_notice_domain' => env('APP_NOTICE_DOMAIN', 'MARVEL_'),

    'dummy_data_path' => env('DUMMY_DATA_PATH', 'pickbazar'),

    'default_language' => env('DEFAULT_LANGUAGE', 'en'),

    'translation_enabled' => env('TRANSLATION_ENABLED', true),

    'default_currency' => env('DEFAULT_CURRENCY', 'USD'),

    'active_payment_gateway' => env('ACTIVE_PAYMENT_GATEWAY', 'stripe'),

    'razorpay' => [
        'key_id'         => env('RAZORPAY_KEY_ID'),
        'key_secret'     => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET_KEY')
    ],

    'mollie' => [
        'mollie_key'  => env('MOLLIE_KEY'),
        'webhook_url' => env('MOLLIE_WEBHOOK_URL'),
    ],

    'stripe' => [
        'api_secret'     => env('STRIPE_API_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET_KEY')
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
    ],

    'xendit' => [
        'api_key'               => env('API_KEY'),
        'webhook_url'           => env('XENDIT_WEBHOOK_URL'),
        'xendit_callback_token' => env('XENDIT_CALLBACK_TOKEN'),
    ],

    'paymongo' => [
        'public_key' => env('PAYMONGO_SECRET_KEY'),
        'secret_key' => env('PAYMONGO_PUBLIC_KEY'),
        'webhook_sig' => env('PAYMONGO_WEBHOOK_SIG'),
    ],

    'paypal' => [
        'mode'           => env('PAYPAL_MODE', 'sandbox'), // Can only be 'sandbox' Or 'live'. If empty or invalid, 'live' will be used.
        'sandbox'        => [
            'client_id'     => env('PAYPAL_SANDBOX_CLIENT_ID', ''),
            'client_secret' => env('PAYPAL_SANDBOX_CLIENT_SECRET', ''),
        ],
        'live'           => [
            'client_id'     => env('PAYPAL_LIVE_CLIENT_ID', ''),
            'client_secret' => env('PAYPAL_LIVE_CLIENT_SECRET', ''),
        ],
        'payment_action' => env('PAYPAL_PAYMENT_ACTION', 'Sale'), // Can only be 'Sale', 'Authorization' or 'Order'
        'webhook_id'     => env('PAYPAL_WEBHOOK_ID'),
        'currency'       => env('PAYPAL_CURRENCY', 'USD'),
        'notify_url'     => env('PAYPAL_NOTIFY_URL', ''), // Change this accordingly for your application.
        'locale'         => env('PAYPAL_LOCALE', 'en_US'), // force gateway language  i.e. it_IT, es_ES, en_US ... (for express checkout only)
        'validate_ssl'   => env('PAYPAL_VALIDATE_SSL', true), // Validate SSL when creating api client.
    ],
    'sslcommerz' => [
        'store_id'       => env('SSLC_STORE_ID'),
        'store_password' => env('SSLC_STORE_PASSWORD'),
    ],
    'iyzico' => [
        'api_Key'    => env('IYZIPAY_API_KEY', ''),
        'secret_Key' => env('IYZIPAY_SECRET_KEY', ''),
        'baseUrl'    => env('IYZIPAY_BASE_URL', 'https://sandbox-api.iyzipay.com'),
    ],
    'bitpay' => [
        'mode'               => env('BITPAY_IS_PRODUCTION', false),
        'private_key_path'   => env('BITPAY_PRIVATE_KEY_PATH', storage_path('app/private/PrivateKeyName.key')),
        'private_key_secret' => env('BITPAY_KEY_STORAGE_PASSWORD'),
        'enable_merchant'    => env('BITPAY_ENABLE_MERCHANT', true),
        'enable_payout'      => env('BITPAY_ENABLE_PAYOUT', false),
        'generate_json'      => env('BITPAY_GENERATE_JSON_FILE', true),
        'generate_yml'       => env('BITPAY_GENERATE_YML_FILE', false),
        'config_file'        => [
            'json' => env('BITPAY_FULL_PATH_TO_THE_JSON_CONFIG_FILE', storage_path('app/private/BitPay.config.json')),
            'yml'  => env('BITPAY_FULL_PATH_TO_THE_YML_CONFIG_FILE', storage_path('app/private/BitPay.config.yml')),
        ],
    ],
    'coinbase' => [
        'api_key' => env('COINBASE_API__KEY'),
    ],

    'bkash' => [
        'app_Key'      => env('BKASH_APP_KEY', ''),
        'app_secret'   => env('BKASH_APP_SECRET', ''),
        'username'     => env('BKASH_USERNAME', ''),
        'password'     => env('BKASH_PASSWORD', ''),
        'callback_url' => env('BKASH_CALLBACK_URL', 'http://127.0.0.1:8000/bkash/callback'),
    ],
    'flutterwave' => [
        'public_key' => env('FLW_PUBLIC_KEY'),
        'secret_key' => env('FLW_SECRET_KEY'),
        'secret_hash' => env('FLW_SECRET_HASH'),
    ],

    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'sandbox' => env('ZARINPAL_SANDBOX', true),
        'callback_url' => env('ZARINPAL_CALLBACK_URL'),
        'use_toman' => env('ZARINPAL_USE_TOMAN', false),
        'description' => env('ZARINPAL_PAYMENT_DESCRIPTION', 'پرداخت سفارش #{order_id}'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Drivers Configuration
    |--------------------------------------------------------------------------
    |
    | Configure storage drivers for file uploads (images, videos, digital files)
    |
    */
    'storage' => [
        'default_driver' => env('STORAGE_DEFAULT_DRIVER', 'local'),
        
        'type_mapping' => [
            'image' => env('STORAGE_IMAGE_DRIVER', 'local'),
            'video' => env('STORAGE_VIDEO_DRIVER', 'local'),
            'digital_file' => env('STORAGE_DIGITAL_FILE_DRIVER', 'local'),
            'document' => env('STORAGE_DOCUMENT_DRIVER', 'local'),
        ],
        
        // Token expiration settings
        'token_expiration' => [
            'enabled' => env('STORAGE_TOKEN_EXPIRATION_ENABLED', false),
            'default_ttl' => env('STORAGE_TOKEN_DEFAULT_TTL', 86400), // 24 hours in seconds
        ],

        'drivers' => [
            'local' => [
                'enabled' => true,
            ],

            'telegram' => [
                'enabled' => env('TELEGRAM_STORAGE_ENABLED', false),
                'api_id' => env('TELEGRAM_API_ID'),
                'api_hash' => env('TELEGRAM_API_hash'),
                'phone' => env('TELEGRAM_PHONE'),
                'channel_id' => env('TELEGRAM_CHANNEL_ID'),
            ],

            'google_drive' => [
                'enabled' => env('GOOGLE_DRIVE_ENABLED', false),
                'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
                'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
                'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID', 'root'),
                'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI'),
            ],

            'ftp' => [
                'enabled' => env('FTP_STORAGE_ENABLED', false),
                'host' => env('FTP_HOST'),
                'username' => env('FTP_USERNAME'),
                'password' => env('FTP_PASSWORD'),
                'port' => env('FTP_PORT', 21),
                'root' => env('FTP_ROOT', '/'),
                'ssl' => env('FTP_SSL', false),
                'timeout' => env('FTP_TIMEOUT', 30),
                'passive' => env('FTP_PASSIVE', true),
                'base_url' => env('FTP_BASE_URL'),
            ],
        ],
    ],

    'openai' => [
        'secret_Key' => env('OPENAI_SECRET_KEY'),
    ],

    'pusher' => [
        'enabled' => env('PUSHER_ENABLED', false),
    ]
];
