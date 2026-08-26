<?php

return [

    'metrics' => ['token' => env('METRICS_TOKEN')],

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

    'asterisk_bridge' => [
        'url' => env('ASTERISK_BRIDGE_URL', 'http://asterisk-bridge:8090'),
        'token' => env('ASTERISK_BRIDGE_TOKEN'),
        'webhook_secret' => env('ASTERISK_BRIDGE_WEBHOOK_SECRET'),
        'timeout' => (int) env('ASTERISK_BRIDGE_TIMEOUT', 8),
        'manager_host' => env('ASTERISK_AMI_HOST', 'asterisk'),
        'manager_port' => (int) env('ASTERISK_AMI_PORT', 5038),
        'manager_username' => env('ASTERISK_AMI_USERNAME', 'thconect'),
        'manager_secret' => env('ASTERISK_AMI_SECRET'),
        'webrtc_stun_url' => env('WEBRTC_STUN_URL'),
    ],

];
