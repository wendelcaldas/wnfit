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

    'messaging' => [
        'mode' => env('MESSAGING_MODE', 'manual'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'validate_webhook_signature' => env('TWILIO_VALIDATE_WEBHOOK_SIGNATURE', env('APP_ENV') === 'production'),
        'whatsapp' => [
            'driver' => env('TWILIO_WHATSAPP_DRIVER', env('APP_ENV') === 'testing' ? 'fake' : 'twilio'),
            'from' => env('TWILIO_WHATSAPP_FROM'),
            'status_callback_url' => env('TWILIO_WHATSAPP_STATUS_CALLBACK_URL', rtrim((string) env('APP_URL'), '/').'/api/webhooks/twilio/whatsapp/status'),
            'templates' => [
                'charge_reminder' => env('TWILIO_WHATSAPP_CHARGE_TEMPLATE_SID'),
                'student_welcome' => env('TWILIO_WHATSAPP_WELCOME_TEMPLATE_SID'),
            ],
        ],
    ],

];
