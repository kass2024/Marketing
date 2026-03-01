<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Controller Imports
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\FacebookAuthController;
use App\Http\Controllers\Admin\FaqController;

/* Client */
use App\Http\Controllers\Client\{
    DashboardController,
    CampaignController,
    ChatbotController,
    TemplateController,
    ConversationController,
    BillingController,
    MetaConnectionController
};

/* Admin */
use App\Http\Controllers\Admin\{
    AdminDashboardController,
    AdminClientController,
    AdminMetaController
};


/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::view('/', 'welcome')->name('home');


/*
|--------------------------------------------------------------------------
| AUTH (FACEBOOK OAUTH)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')
    ->middleware('guest')
    ->as('facebook.')
    ->group(function () {

        Route::get('/facebook', [FacebookAuthController::class, 'redirect'])
            ->name('redirect');

        Route::get('/facebook/callback', [FacebookAuthController::class, 'callback'])
            ->name('callback');
    });


/*
|--------------------------------------------------------------------------
| ROLE-BASED DASHBOARD REDIRECT
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])
    ->get('/dashboard', function () {

        return match (true) {
            auth()->user()->isAdmin()  => redirect()->route('admin.dashboard'),
            auth()->user()->isClient() => redirect()->route('client.dashboard'),
            default                    => abort(403),
        };

    })->name('dashboard');


/*
|--------------------------------------------------------------------------
| CLIENT AREA (TENANT PANEL)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'role:client'])
    ->prefix('client')
    ->as('client.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');


        /*
        |--------------------------------------------------------------------------
        | Campaigns
        |--------------------------------------------------------------------------
        */
        Route::resource('campaigns', CampaignController::class);

        Route::patch('campaigns/{campaign}/activate',
            [CampaignController::class, 'activate'])
            ->name('campaigns.activate');

        Route::patch('campaigns/{campaign}/pause',
            [CampaignController::class, 'pause'])
            ->name('campaigns.pause');


        /*
        |--------------------------------------------------------------------------
        | Chatbots & Templates
        |--------------------------------------------------------------------------
        */
        Route::resource('chatbots', ChatbotController::class);
        Route::resource('templates', TemplateController::class);


        /*
        |--------------------------------------------------------------------------
        | Inbox
        |--------------------------------------------------------------------------
        */
        Route::prefix('inbox')->as('inbox.')->group(function () {

            Route::get('/', [ConversationController::class, 'index'])
                ->name('index');

            Route::get('/{conversation}', [ConversationController::class, 'show'])
                ->name('show');

            Route::post('/{conversation}/send',
                [ConversationController::class, 'send'])
                ->name('send');
        });


        /*
        |--------------------------------------------------------------------------
        | Billing
        |--------------------------------------------------------------------------
        */
        Route::prefix('billing')->as('billing.')->group(function () {

            Route::get('/', [BillingController::class, 'index'])
                ->name('index');

            Route::post('/checkout',
                [BillingController::class, 'checkout'])
                ->name('checkout');

            Route::post('/cancel',
                [BillingController::class, 'cancel'])
                ->name('cancel');
        });


        /*
        |--------------------------------------------------------------------------
        | Client Meta OAuth
        |--------------------------------------------------------------------------
        */
        Route::prefix('meta')->as('meta.')->group(function () {

            Route::get('/', fn () => view('client.meta.index'))
                ->name('index');

            Route::get('/connect',
                [MetaConnectionController::class, 'connect'])
                ->name('connect');

            Route::get('/callback',
                [MetaConnectionController::class, 'callback'])
                ->name('callback');

            Route::post('/disconnect',
                [MetaConnectionController::class, 'disconnect'])
                ->name('disconnect');
        });
    });



/*
|--------------------------------------------------------------------------
| ADMIN AREA (PLATFORM OWNER)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */
        Route::get('/dashboard',
            [AdminDashboardController::class, 'index'])
            ->name('dashboard');


        /*
        |--------------------------------------------------------------------------
        | Client Management
        |--------------------------------------------------------------------------
        */
        Route::resource('clients', AdminClientController::class);

        Route::get('clients/{client}/impersonate',
            [AdminClientController::class, 'impersonate'])
            ->name('clients.impersonate');

        Route::post('impersonation/stop',
            [AdminClientController::class, 'stopImpersonation'])
            ->name('impersonation.stop');


        /*
        |--------------------------------------------------------------------------
        | Master Meta Business
        |--------------------------------------------------------------------------
        */
        Route::prefix('meta')->as('meta.')->group(function () {

            Route::get('/',
                [AdminMetaController::class, 'index'])
                ->name('index');

            Route::get('/connect',
                [AdminMetaController::class, 'connect'])
                ->name('connect');

            Route::get('/callback',
                [AdminMetaController::class, 'callback'])
                ->name('callback');

            Route::post('/disconnect',
                [AdminMetaController::class, 'disconnect'])
                ->name('disconnect');
        });


        /*
        |--------------------------------------------------------------------------
        | System
        |--------------------------------------------------------------------------
        */
        Route::view('/system', 'admin.system.index')
            ->name('system.index');

        Route::view('/settings', 'admin.settings.index')
            ->name('settings.index');
    });



/*
|--------------------------------------------------------------------------
| USER PROFILE
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});
Route::prefix('admin')
    ->middleware(['auth'])
    ->name('admin.')
    ->group(function () {

        // INDEX
        Route::get('/faq', [FaqController::class, 'index'])
            ->name('faq.index');

        // CREATE
        Route::get('/faq/create', [FaqController::class, 'create'])
            ->name('faq.create');

        // STORE
        Route::post('/faq', [FaqController::class, 'store'])
            ->name('faq.store');

        // EDIT
        Route::get('/faq/{faq}/edit', [FaqController::class, 'edit'])
            ->name('faq.edit');

        // UPDATE
        Route::put('/faq/{faq}', [FaqController::class, 'update'])
            ->name('faq.update');

        // DELETE
        Route::delete('/faq/{faq}', [FaqController::class, 'destroy'])
            ->name('faq.destroy');

    });
require __DIR__.'/auth.php';