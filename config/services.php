<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global API Settings
    |--------------------------------------------------------------------------
    */
    'api' => [
        'timeout' => (int) env('API_TIMEOUT', 30),
        'retry'   => (int) env('API_RETRY_ATTEMPTS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Facebook Login (User Auth Only)
    |--------------------------------------------------------------------------
    */
    'facebook_login' => [
        'client_id'     => env('FB_APP_ID'),
        'client_secret' => env('FB_APP_SECRET'),
        'redirect'      => env('FB_REDIRECT_URI'),

        'graph_version' => env('FB_GRAPH_VERSION', 'v19.0'),
        'graph_url'     => env('FB_GRAPH_URL', 'https://graph.facebook.com'),
        'oauth_url'     => env('FB_OAUTH_URL', 'https://www.facebook.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | META PLATFORM APP (Master Business)
    |--------------------------------------------------------------------------
    */
    'meta' => [

        'app_id'       => env('META_APP_ID'),
        'app_secret'   => env('META_APP_SECRET'),
        'redirect_uri' => env('META_REDIRECT_URI'),

        'graph_version' => env('META_GRAPH_VERSION', 'v19.0'),
        'graph_url'     => env('META_GRAPH_URL', 'https://graph.facebook.com'),
        'oauth_url'     => env('META_OAUTH_URL', 'https://www.facebook.com'),

        /*
        |--------------------------------------------------------------------------
        | Required Permissions
        |--------------------------------------------------------------------------
        */
        'required_permissions' => [
            'ads_management',
            'business_management',
            'whatsapp_business_management',
            'whatsapp_business_messaging',
        ],

        /*
        |--------------------------------------------------------------------------
        | Token Management
        |--------------------------------------------------------------------------
        */
        'token_refresh_before_days' => (int) env('META_TOKEN_REFRESH_BEFORE_DAYS', 5),

        'long_lived_exchange_url' => env(
            'META_TOKEN_EXCHANGE_URL',
            'https://graph.facebook.com/oauth/access_token'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    | In SaaS mode → token comes from DB (PlatformMetaConnection)
    |--------------------------------------------------------------------------
    */
    'whatsapp' => [

        'graph_version' => env('META_GRAPH_VERSION', 'v19.0'),
        'graph_url'     => env('META_GRAPH_URL', 'https://graph.facebook.com'),

        'timeout' => (int) env('WHATSAPP_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Webhook Security
    |--------------------------------------------------------------------------
    */
    'whatsapp_webhook' => [

        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret'   => env('WHATSAPP_APP_SECRET'),

        'signature_header' => env(
            'META_SIGNATURE_HEADER',
            'X-Hub-Signature-256'
        ),

        'hash_algo' => 'sha256',
    ],
    
'openai' => [
    'key'   => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
],
    /*
    |--------------------------------------------------------------------------
    | Ads Defaults
    |--------------------------------------------------------------------------
    */
    'ads' => [

        'default_currency' => env('ADS_DEFAULT_CURRENCY', 'USD'),
        'default_timezone' => env('ADS_DEFAULT_TIMEZONE', 'UTC'),

        'default_objective' => env(
            'ADS_DEFAULT_OBJECTIVE',
            'OUTCOME_TRAFFIC'
        ),

        'min_daily_budget'     => (int) env('ADS_MIN_DAILY_BUDGET', 100),
        'min_lifetime_budget'  => (int) env('ADS_MIN_LIFETIME_BUDGET', 1000),
    ],

];