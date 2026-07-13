<?php

/**
 * Platform (main account) credentials — sourced from .env only.
 * Tenant businesses store their Meta/WhatsApp settings in platform_meta_connections.
 */
return [

    'name' => env('APP_NAME', 'WABA Platform'),

    'meta' => [
        'app_id'             => env('META_APP_ID'),
        'app_secret'         => env('META_APP_SECRET'),
        'redirect_uri'       => env('META_REDIRECT_URI'),
        'system_user_token'  => env('META_SYSTEM_USER_TOKEN'),
        'ad_account_id'      => env('META_AD_ACCOUNT_ID'),
        'page_id'            => env('META_PAGE_ID'),
        'page_name'          => env('META_PAGE_NAME', 'Platform Page'),
        'instagram_user_id'  => env('META_INSTAGRAM_USER_ID'),
        'instagram_username' => env('META_INSTAGRAM_USERNAME'),
        /** Meta Business Manager ID (not the WABA id). */
        'business_id'        => env('META_BUSINESS_ID', env('META_BUSINESS_MANAGER_ID')),
        'graph_version'      => env('META_GRAPH_VERSION', 'v19.0'),
        'graph_url'          => env('META_GRAPH_URL', 'https://graph.facebook.com'),
    ],

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token'    => env('WHATSAPP_ACCESS_TOKEN'),
        /** WhatsApp Business Account (WABA) ID */
        'business_id'     => env('WHATSAPP_BUSINESS_ID'),
        'phone_number'    => env('WHATSAPP_PHONE_NUMBER'),
        'verify_token'    => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret'      => env('WHATSAPP_APP_SECRET'),
        /** Two-step verification PIN for Cloud API register after OTP (set in WhatsApp Manager). */
        'registration_pin' => env('WHATSAPP_REGISTRATION_PIN', '123456'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Publishing model
    |--------------------------------------------------------------------------
    | platform_controls_api: Meta API token + ad account always from .env (main account).
    | tenants_share_platform_meta: when true, all tenants use the same page/WA as .env
    |   (legacy). When false (default), each tenant has its own Facebook Page + business
    |   WhatsApp number for ads, but publishing is still done via the main account token.
    */
    'platform_controls_api' => env('PLATFORM_CONTROLS_API', true),

    'tenants_share_platform_meta' => env('PLATFORM_TENANTS_SHARE_META', false),

    /**
     * Soft auto-sync throttle (seconds) for non-WhatsApp page loads.
     * WhatsApp BM + Ad Studio WhatsApp always force-sync (bypass TTL).
     */
    'meta_auto_sync_ttl' => (int) env('META_AUTO_SYNC_TTL', 120),

    /** When true, Ad Studio create + WhatsApp pages always force Meta phone sync. */
    'whatsapp_always_sync' => filter_var(env('WHATSAPP_ALWAYS_SYNC', true), FILTER_VALIDATE_BOOLEAN),

];
