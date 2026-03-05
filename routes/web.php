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
use App\Http\Controllers\Admin\AdsManagerController;

/* CLIENT CONTROLLERS */

use App\Http\Controllers\Client\{
    DashboardController,
    CampaignController,
    ChatbotController,
    TemplateController,
    ConversationController,
    BillingController,
    MetaConnectionController
};

/* ADMIN CONTROLLERS */

use App\Http\Controllers\Admin\{
    AdminDashboardController,
    AdminClientController,
    AdminMetaController,
    AdAccountController,
    CampaignController as AdminCampaignController,
    AdSetController,
    AdController,
    AnalyticsController,
    CreativeController
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

        Route::get('/facebook', [FacebookAuthController::class,'redirect'])
            ->name('redirect');

        Route::get('/facebook/callback', [FacebookAuthController::class,'callback'])
            ->name('callback');
    });


/*
|--------------------------------------------------------------------------
| ROLE BASED DASHBOARD
|--------------------------------------------------------------------------
*/

Route::middleware(['auth','verified'])
    ->get('/dashboard', function () {

        return match (true) {

            auth()->user()->isAdmin()  => redirect()->route('admin.dashboard'),
            auth()->user()->isClient() => redirect()->route('client.dashboard'),
            default => abort(403)

        };

    })->name('dashboard');


/*
|--------------------------------------------------------------------------
| CLIENT PANEL
|--------------------------------------------------------------------------
*/

Route::middleware(['auth','verified','role:client'])
    ->prefix('client')
    ->as('client.')
    ->group(function () {

        Route::get('/dashboard',[DashboardController::class,'index'])
            ->name('dashboard');

        Route::resource('campaigns',CampaignController::class);

        Route::patch('campaigns/{campaign}/activate',
            [CampaignController::class,'activate'])
            ->name('campaigns.activate');

        Route::patch('campaigns/{campaign}/pause',
            [CampaignController::class,'pause'])
            ->name('campaigns.pause');

        Route::resource('chatbots',ChatbotController::class);
        Route::resource('templates',TemplateController::class);


        /*
        |--------------------------------------------------------------------------
        | CLIENT INBOX
        |--------------------------------------------------------------------------
        */

        Route::prefix('inbox')->as('inbox.')->group(function () {

            Route::get('/',[ConversationController::class,'index'])->name('index');

            Route::get('/{conversation}',[ConversationController::class,'show'])->name('show');

            Route::post('/{conversation}/send',
                [ConversationController::class,'send'])->name('send');
        });


        /*
        |--------------------------------------------------------------------------
        | BILLING
        |--------------------------------------------------------------------------
        */

        Route::prefix('billing')->as('billing.')->group(function () {

            Route::get('/',[BillingController::class,'index'])->name('index');

            Route::post('/checkout',[BillingController::class,'checkout'])->name('checkout');

            Route::post('/cancel',[BillingController::class,'cancel'])->name('cancel');
        });


        /*
        |--------------------------------------------------------------------------
        | META CONNECTION
        |--------------------------------------------------------------------------
        */

        Route::prefix('meta')->as('meta.')->group(function () {

            Route::get('/',fn() => view('client.meta.index'))->name('index');

            Route::get('/connect',[MetaConnectionController::class,'connect'])->name('connect');

            Route::get('/callback',[MetaConnectionController::class,'callback'])->name('callback');

            Route::post('/disconnect',[MetaConnectionController::class,'disconnect'])->name('disconnect');
        });
    });



/*
|--------------------------------------------------------------------------
| ADMIN PANEL
|--------------------------------------------------------------------------
*/

Route::middleware(['auth','verified','role:admin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {


        /*
        |--------------------------------------------------------------------------
        | DASHBOARD
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard',[AdminDashboardController::class,'index'])
            ->name('dashboard');


        /*
        |--------------------------------------------------------------------------
        | CLIENT MANAGEMENT
        |--------------------------------------------------------------------------
        */

        Route::resource('clients',AdminClientController::class);

        Route::get('clients/{client}/impersonate',
            [AdminClientController::class,'impersonate'])
            ->name('clients.impersonate');

        Route::post('impersonation/stop',
            [AdminClientController::class,'stopImpersonation'])
            ->name('impersonation.stop');


        /*
        |--------------------------------------------------------------------------
        | META BUSINESS
        |--------------------------------------------------------------------------
        */

        Route::prefix('meta')->as('meta.')->group(function () {

            Route::get('/',[AdminMetaController::class,'index'])->name('index');

            Route::get('/connect',[AdminMetaController::class,'connect'])->name('connect');

            Route::get('/callback',[AdminMetaController::class,'callback'])->name('callback');

            Route::post('/disconnect',[AdminMetaController::class,'disconnect'])->name('disconnect');
        });


        /*
        |--------------------------------------------------------------------------
        | FAQ
        |--------------------------------------------------------------------------
        */

        Route::resource('faq',FaqController::class);

        Route::get('faq/template',[FaqController::class,'downloadTemplate'])->name('faq.template');

        Route::post('faq/import',[FaqController::class,'import'])->name('faq.import');


        /*
        |--------------------------------------------------------------------------
        | ADMIN INBOX
        |--------------------------------------------------------------------------
        */

        Route::controller(InboxController::class)
            ->prefix('inbox')
            ->as('inbox.')
            ->group(function () {

                Route::get('/','index')->name('index');
                Route::post('{conversation}/reply','reply')->name('reply');
                Route::post('{conversation}/toggle','toggle')->name('toggle');
                Route::post('{conversation}/close','close')->name('close');
            });


        /*
        |--------------------------------------------------------------------------
        | META ADS SYSTEM
        |--------------------------------------------------------------------------
        */

        Route::resource('accounts',AdAccountController::class)->names('accounts');

        Route::resource('campaigns',AdminCampaignController::class)->names('campaigns');


        /*
        |--------------------------------------------------------------------------
        | ADSETS
        |--------------------------------------------------------------------------
        */

        Route::resource('adsets',AdSetController::class)
            ->except(['create','show'])
            ->names('adsets');

        Route::get(
            'campaigns/{campaign}/adsets/create',
            [AdSetController::class,'create']
        )->name('campaigns.adsets.create');


        /*
        |--------------------------------------------------------------------------
        | ADS
        |--------------------------------------------------------------------------
        */

        Route::resource('ads',AdController::class)->names('ads');

        Route::resource('creatives',CreativeController::class)->names('creatives');


        /*
        |--------------------------------------------------------------------------
        | META ADS MANAGER
        |--------------------------------------------------------------------------
        */

        Route::get('/ads-manager',[AdsManagerController::class,'index'])
            ->name('ads.manager');

        Route::get('/campaigns/{campaign}/adsets',
            [AdsManagerController::class,'adsets'])
            ->name('ads.manager.adsets');

        Route::get('/adsets/{adset}/ads',
            [AdsManagerController::class,'ads'])
            ->name('ads.manager.ads');


        /*
        |--------------------------------------------------------------------------
        | ANALYTICS
        |--------------------------------------------------------------------------
        */

        Route::get('analytics',[AnalyticsController::class,'index'])
            ->name('analytics.index');


        /*
        |--------------------------------------------------------------------------
        | SYSTEM
        |--------------------------------------------------------------------------
        */

        Route::view('/system','admin.system.index')->name('system.index');

        Route::view('/settings','admin.settings.index')->name('settings.index');

    });



/*
|--------------------------------------------------------------------------
| PROFILE
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/profile',[ProfileController::class,'edit'])->name('profile.edit');

    Route::patch('/profile',[ProfileController::class,'update'])->name('profile.update');

    Route::delete('/profile',[ProfileController::class,'destroy'])->name('profile.destroy');
});


require __DIR__.'/auth.php';