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
use App\Http\Controllers\Admin\InboxController;

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
    AdminMetaController,
    AdAccountController,
    CampaignController as AdminCampaignController,
    AdSetController,
    AdController,
    AnalyticsController
};


/*
|--------------------------------------------------------------------------
| PUBLIC
|--------------------------------------------------------------------------
*/

Route::view('/', 'welcome')->name('home');


/*
|--------------------------------------------------------------------------
| FACEBOOK AUTH
|--------------------------------------------------------------------------
*/

Route::prefix('auth')
    ->middleware('guest')
    ->as('facebook.')
    ->group(function () {

        Route::get('/facebook', [FacebookAuthController::class, 'redirect'])->name('redirect');
        Route::get('/facebook/callback', [FacebookAuthController::class, 'callback'])->name('callback');
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
| CLIENT PANEL
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'role:client'])
    ->prefix('client')
    ->as('client.')
    ->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('campaigns', CampaignController::class);
        Route::patch('campaigns/{campaign}/activate', [CampaignController::class, 'activate'])->name('campaigns.activate');
        Route::patch('campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');

        Route::resource('chatbots', ChatbotController::class);
        Route::resource('templates', TemplateController::class);

        Route::prefix('inbox')->as('inbox.')->group(function () {
            Route::get('/', [ConversationController::class, 'index'])->name('index');
            Route::get('/{conversation}', [ConversationController::class, 'show'])->name('show');
            Route::post('/{conversation}/send', [ConversationController::class, 'send'])->name('send');
        });

        Route::prefix('billing')->as('billing.')->group(function () {
            Route::get('/', [BillingController::class, 'index'])->name('index');
            Route::post('/checkout', [BillingController::class, 'checkout'])->name('checkout');
            Route::post('/cancel', [BillingController::class, 'cancel'])->name('cancel');
        });

        Route::prefix('meta')->as('meta.')->group(function () {
            Route::get('/', fn () => view('client.meta.index'))->name('index');
            Route::get('/connect', [MetaConnectionController::class, 'connect'])->name('connect');
            Route::get('/callback', [MetaConnectionController::class, 'callback'])->name('callback');
            Route::post('/disconnect', [MetaConnectionController::class, 'disconnect'])->name('disconnect');
        });
    });


/*
|--------------------------------------------------------------------------
| ADMIN PANEL (SINGLE CLEAN BLOCK)
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
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Clients
        |--------------------------------------------------------------------------
        */
        Route::resource('clients', AdminClientController::class);
        Route::get('clients/{client}/impersonate', [AdminClientController::class, 'impersonate'])->name('clients.impersonate');
        Route::post('impersonation/stop', [AdminClientController::class, 'stopImpersonation'])->name('impersonation.stop');

        /*
        |--------------------------------------------------------------------------
        | Master Meta Business
        |--------------------------------------------------------------------------
        */
        Route::prefix('meta')->as('meta.')->group(function () {
            Route::get('/', [AdminMetaController::class, 'index'])->name('index');
            Route::get('/connect', [AdminMetaController::class, 'connect'])->name('connect');
            Route::get('/callback', [AdminMetaController::class, 'callback'])->name('callback');
            Route::post('/disconnect', [AdminMetaController::class, 'disconnect'])->name('disconnect');
        });

        /*
        |--------------------------------------------------------------------------
        | FAQ
        |--------------------------------------------------------------------------
        */
        Route::resource('faq', FaqController::class);
        Route::get('faq/template', [FaqController::class, 'downloadTemplate'])->name('faq.template');
        Route::post('faq/import', [FaqController::class, 'import'])->name('faq.import');

        /*
        |--------------------------------------------------------------------------
        | Admin Inbox
        |--------------------------------------------------------------------------
        */
        Route::controller(InboxController::class)
            ->prefix('inbox')
            ->as('inbox.')
            ->group(function () {

                Route::get('/', 'index')->name('index');
                Route::post('{conversation}/reply', 'reply')->name('reply');
                Route::post('{conversation}/toggle', 'toggle')->name('toggle');
                Route::post('{conversation}/close', 'close')->name('close');
            });

        /*
        |--------------------------------------------------------------------------
        | Ads Management (Enterprise Block)
        |--------------------------------------------------------------------------
        */
        Route::resource('accounts', AdAccountController::class);
        Route::resource('campaigns', AdminCampaignController::class);
        Route::resource('adsets', AdSetController::class);
        Route::resource('ads', AdController::class);

        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

        /*
        |--------------------------------------------------------------------------
        | System
        |--------------------------------------------------------------------------
        */
        Route::view('/system', 'admin.system.index')->name('system.index');
        Route::view('/settings', 'admin.settings.index')->name('settings.index');
    });


/*
|--------------------------------------------------------------------------
| USER PROFILE
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


require __DIR__.'/auth.php';