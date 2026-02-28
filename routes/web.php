<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Controller Imports
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\FacebookAuthController;

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

/* Webhooks */
use App\Http\Controllers\Webhooks\MetaWebhookController;


/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::view('/', 'welcome')->name('home');


/*
|--------------------------------------------------------------------------
| FACEBOOK OAUTH
|--------------------------------------------------------------------------
*/

Route::prefix('auth')
    ->middleware('guest')
    ->name('facebook.')
    ->group(function () {

        Route::get('/facebook', [FacebookAuthController::class, 'redirect'])
            ->name('redirect');

        Route::get('/facebook/callback', [FacebookAuthController::class, 'callback'])
            ->name('callback');
    });


/*
|--------------------------------------------------------------------------
| DASHBOARD REDIRECT BASED ON ROLE
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])
    ->get('/dashboard', function () {

        $user = auth()->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isClient()) {
            return redirect()->route('client.dashboard');
        }

        abort(403);
    })->name('dashboard');


/*
|--------------------------------------------------------------------------
| CLIENT AREA (TENANT)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'role:client'])
    ->prefix('client')
    ->name('client.')
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
        Route::prefix('inbox')->name('inbox.')->group(function () {

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
        Route::prefix('billing')->name('billing.')->group(function () {

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
        Route::prefix('meta')->name('meta.')->group(function () {

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
    ->name('admin.')
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
        | Platform Meta (Master Business)
        |--------------------------------------------------------------------------
        */
        Route::prefix('meta')->name('meta.')->group(function () {

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
        | System & Settings
        |--------------------------------------------------------------------------
        */
        Route::view('/system', 'admin.system.index')
            ->name('system.index');

        Route::view('/settings', 'admin.settings.index')
            ->name('settings.index');
    });



/*
|--------------------------------------------------------------------------
| META WEBHOOK (PUBLIC - SECURE)
|--------------------------------------------------------------------------
*/

Route::prefix('webhook')
    ->middleware('throttle:60,1')
    ->group(function () {

        Route::get('/meta', [MetaWebhookController::class, 'verify']);
        Route::post('/meta', [MetaWebhookController::class, 'handle']);
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


require __DIR__.'/auth.php';