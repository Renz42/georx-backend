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

    'maxim' => [
        'api_url' => env('MAXIM_API_URL', 'https://api.maxim.com'),
        'api_key' => env('MAXIM_API_KEY'),
        'sandbox' => env('MAXIM_SANDBOX', true),
        'webhook_secret' => env('MAXIM_WEBHOOK_SECRET'),
    ],

    'supabase' => [
        'url' => rtrim(trim((string) env('SUPABASE_URL', '')), '/') ?: null,
        'secret_key' => trim((string) env('SUPABASE_SECRET_KEY', '')) ?: null,
        'service_role_key' => trim((string) env('SUPABASE_SERVICE_ROLE_KEY', ''))
            ?: (trim((string) env('SUPABASE_SERVICE_KEY', '')) ?: null),
        'service_key' => trim((string) env('SUPABASE_SERVICE_KEY', '')) ?: null,
        'key' => trim((string) env('SUPABASE_KEY', '')) ?: null,
        'anon_key' => trim((string) env('SUPABASE_ANON_KEY', '')) ?: null,
        'publishable_key' => trim((string) env('SUPABASE_PUBLISHABLE_KEY', '')) ?: null,
    ],

    'notifications' => [
        'sync_restock_dispatch' => filter_var(
            env('NOTIFICATIONS_SYNC_RESTOCK_DISPATCH', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'expo_push_enabled' => filter_var(
            env('EXPO_PUSH_ENABLED', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'expo_push_url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
        'expo_push_timeout' => (int) env('EXPO_PUSH_TIMEOUT', 5),
        'restock_dedup_seconds' => (int) env('RESTOCK_NOTIFY_DEDUP_SECONDS', 60),
    ],

];
