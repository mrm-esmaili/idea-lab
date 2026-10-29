<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sms' => [
        // smsir برای ارسال واقعی؛ log فقط برای توسعه محلی (در production مجاز نیست)
        'driver' => env('SMS_DRIVER', 'log'),

        'smsir' => [
            'api_key' => env('SMSIR_API_KEY'),
            'base_url' => env('SMSIR_BASE_URL', 'https://api.sms.ir/v1'),
            'timeout' => (int) env('SMSIR_TIMEOUT', 10),
            // خط اختصاصی برای ارسال متن آزاد (ارسال گروهی و دستی)
            'line_number' => env('SMSIR_LINE_NUMBER'),
        ],
    ],

    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),

        'sandbox' => env('ZARINPAL_SANDBOX', true),

        // منسوخ: فقط migration افزودن تنظیمات ایده از آن استفاده می‌کند
        'preorder_amount_toman' => env(
            'ZARINPAL_PREORDER_AMOUNT_TOMAN',
            10000
        ),
    ],

];
