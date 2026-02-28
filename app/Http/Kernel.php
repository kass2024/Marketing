<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /*
    |--------------------------------------------------------------------------
    | Global HTTP Middleware Stack
    |--------------------------------------------------------------------------
    | These run during every request.
    */

    protected $middleware = [

        /* Trust Proxies (important for VPS / Cloudflare / Load balancer) */
        \App\Http\Middleware\TrustProxies::class,

        /* CORS */
        \Illuminate\Http\Middleware\HandleCors::class,

        /* Maintenance Mode Protection */
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,

        /* Security */
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,

        /* Sanitization */
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];


    /*
    |--------------------------------------------------------------------------
    | Route Middleware Groups
    |--------------------------------------------------------------------------
    */

    protected $middlewareGroups = [

        /*
        |--------------------------------------------------------------------------
        | Web Middleware Group
        |--------------------------------------------------------------------------
        */
        'web' => [

            /* Cookie Encryption */
            \App\Http\Middleware\EncryptCookies::class,

            /* Add Queued Cookies */
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,

            /* Start Session */
            \Illuminate\Session\Middleware\StartSession::class,

            /* Optional: Authenticate Session (Jetstream / multi-login protection) */
            \Illuminate\Session\Middleware\AuthenticateSession::class,

            /* Share Validation Errors */
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,

            /* CSRF Protection */
            \App\Http\Middleware\VerifyCsrfToken::class,

            /* Route Model Binding */
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        /*
        |--------------------------------------------------------------------------
        | API Middleware Group
        |--------------------------------------------------------------------------
        */
        'api' => [

            /* Optional if using Sanctum */
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,

            /* API Rate Limiting */
            \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',

            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];


    /*
    |--------------------------------------------------------------------------
    | Route Middleware Aliases
    |--------------------------------------------------------------------------
    */

    protected $middlewareAliases = [

        /* Authentication */
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,

        /* Authorization */
        'can' => \Illuminate\Auth\Middleware\Authorize::class,

        /* Guest Redirect */
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,

        /* Password Confirmation */
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,

        /* Signed Routes */
        'signed' => \App\Http\Middleware\ValidateSignature::class,

        /* Email Verification */
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        /* Throttling */
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,

        /* Cache Headers */
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,

        /* Precognition (Live validation) */
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,

        /* Role Middleware (SaaS) */
        'role' => \App\Http\Middleware\RoleMiddleware::class,

        /* Optional Future Permission Middleware */
        // 'permission' => \App\Http\Middleware\PermissionMiddleware::class,
    ];
}