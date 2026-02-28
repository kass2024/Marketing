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
    | Mail Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Facebook Login (User Authentication Only)
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
    | Meta Business Platform App (MASTER APP)
    |--------------------------------------------------------------------------
    | Used for:
    | - Ads API
    | - Business Manager
    | - WhatsApp Business
    | - Platform SaaS connections
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
        | OAuth Settings
        |--------------------------------------------------------------------------
        */

        'oauth' => [
            'response_type' => 'code',
        ],

        /*
        |--------------------------------------------------------------------------
        | Required Permissions (Platform Enforcement)
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
    | WhatsApp Cloud API (Platform Default Fallback)
    |--------------------------------------------------------------------------
    | IMPORTANT:
    | In SaaS mode → client tokens must come from database.
    |--------------------------------------------------------------------------
    */

    'whatsapp' => [

        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token'    => env('WHATSAPP_ACCESS_TOKEN'),
        'business_id'     => env('WHATSAPP_BUSINESS_ID'),

        'graph_version' => env('META_GRAPH_VERSION', 'v19.0'),
        'graph_url'     => env('META_GRAPH_URL', 'https://graph.facebook.com'),

        /*
        |--------------------------------------------------------------------------
        | Performance
        |--------------------------------------------------------------------------
        */

        'max_batch_size' => (int) env('WHATSAPP_MAX_BATCH_SIZE', 50),

        'request_timeout' => (int) env('WHATSAPP_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Security (Meta & WhatsApp)
    |--------------------------------------------------------------------------
    */

    'webhook' => [

        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret'   => env('WHATSAPP_APP_SECRET'),

        'signature_header' => env(
            'META_SIGNATURE_HEADER',
            'X-Hub-Signature-256'
        ),

        'hash_algo' => 'sha256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta Ads Defaults
    |--------------------------------------------------------------------------
    */

    'ads' => [

        'default_currency' => env('ADS_DEFAULT_CURRENCY', 'USD'),
        'default_timezone' => env('ADS_DEFAULT_TIMEZONE', 'UTC'),

        'default_objective' => env(
            'ADS_DEFAULT_OBJECTIVE',
            'OUTCOME_TRAFFIC'
        ),

        /*
        |--------------------------------------------------------------------------
        | Budget in cents
        |--------------------------------------------------------------------------
        */

        'min_daily_budget' => (int) env('ADS_MIN_DAILY_BUDGET', 100),

        'min_lifetime_budget' => (int) env('ADS_MIN_LIFETIME_BUDGET', 1000),
    ],

];