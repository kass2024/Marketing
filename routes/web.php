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

        // Ad Accounts
        Route::resource('accounts', AdAccountController::class)->names('accounts');

        // Campaigns - Full resource with all methods
        Route::resource('campaigns', AdminCampaignController::class)->names('campaigns');

        // Campaign-specific actions
        Route::prefix('campaigns')->name('campaigns.')->group(function () {
            Route::patch('{campaign}/activate', [AdminCampaignController::class, 'activate'])->name('activate');
            Route::patch('{campaign}/pause', [AdminCampaignController::class, 'pause'])->name('pause');
            Route::post('{campaign}/duplicate', [AdminCampaignController::class, 'duplicate'])->name('duplicate');
            Route::get('{campaign}/insights', [AdminCampaignController::class, 'insights'])->name('insights');
            Route::post('{campaign}/sync', [AdminCampaignController::class, 'sync'])
    ->name('sync');
        });


        /*
        |--------------------------------------------------------------------------
        | AD SETS - Complete Resource with All Routes
        |--------------------------------------------------------------------------
        */

        Route::resource('adsets', AdSetController::class)->names('adsets');

        // Additional Ad Set routes (preserves all resource methods)
        Route::prefix('adsets')->name('adsets.')->group(function () {
            
            // Bulk operations
            Route::post('bulk-status-update', [AdSetController::class, 'bulkStatusUpdate'])
                ->name('bulk-status-update');
            
            Route::post('bulk-duplicate', [AdSetController::class, 'bulkDuplicate'])
                ->name('bulk-duplicate');
            
            Route::delete('bulk-destroy', [AdSetController::class, 'bulkDestroy'])
                ->name('bulk-destroy');

            // Individual actions
            Route::patch('{adset}/activate', [AdSetController::class, 'activate'])
                ->name('activate');
            
            Route::patch('{adset}/pause', [AdSetController::class, 'pause'])
                ->name('pause');
            
            Route::post('{adset}/duplicate', [AdSetController::class, 'duplicate'])
                ->name('duplicate');
            
            Route::get('{adset}/insights', [AdSetController::class, 'insights'])
                ->name('insights');
           Route::post('{adset}/sync', [AdSetController::class, 'sync'])
    ->name('sync');
        });

        // Campaign-specific Ad Set creation (THIS IS THE KEY ROUTE YOU NEED)
      Route::get('campaigns/{campaign}/adsets/create', 
    [AdSetController::class, 'create'])
    ->name('campaigns.adsets.create');

        // List Ad Sets by campaign
        Route::get('campaigns/{campaign}/adsets', 
            [AdSetController::class, 'indexByCampaign'])
            ->name('campaigns.adsets.index');
        /*
        |--------------------------------------------------------------------------
        | ADS
        |--------------------------------------------------------------------------
        */

        Route::resource('ads', AdController::class)->names('ads');

        // Additional Ad routes
Route::prefix('ads')->name('ads.')->group(function () {

    Route::post('bulk-status-update', [AdController::class, 'bulkStatusUpdate'])
        ->name('bulk-status-update');

    Route::patch('{ad}/activate', [AdController::class, 'activate'])
        ->name('activate');

    Route::patch('{ad}/pause', [AdController::class, 'pause'])
        ->name('pause');

    Route::post('{ad}/duplicate', [AdController::class, 'duplicate'])
        ->name('duplicate');

    Route::post('{ad}/sync', [AdController::class, 'sync'])
        ->name('sync');
Route::get('{ad}/preview', [AdController::class,'preview'])
    ->name('preview');
    Route::post('{ad}/publish', [AdController::class, 'publish'])
        ->name('publish');

    /* LIVE DASHBOARD ROUTE */
    Route::get('live', [AdController::class,'live'])
        ->name('live');
});

        // Ad Set-specific Ad creation
        Route::get('adsets/{adset}/ads/create', 
            [AdController::class, 'createFromAdSet'])
            ->name('adsets.ads.create');


        /*
|--------------------------------------------------------------------------
| CREATIVES
|--------------------------------------------------------------------------
*/

Route::resource('creatives', CreativeController::class)->names('creatives');

Route::prefix('creatives')->name('creatives.')->group(function () {

    Route::post('{creative}/duplicate', [CreativeController::class, 'duplicate'])
        ->name('duplicate');

    Route::get('{creative}/preview', [CreativeController::class, 'preview'])
        ->name('preview');

    Route::post('{creative}/sync', [CreativeController::class, 'sync'])
        ->name('sync');

    // ADD THESE
    Route::patch('{creative}/activate', [CreativeController::class, 'activate'])
        ->name('activate');

    Route::patch('{creative}/pause', [CreativeController::class, 'pause'])
        ->name('pause');

});
        /*
        |--------------------------------------------------------------------------
        | META ADS MANAGER - Unified Interface
        |--------------------------------------------------------------------------
        */

        Route::prefix('ads-manager')->name('ads.manager.')->group(function () {
            
            Route::get('/', [AdsManagerController::class, 'index'])
                ->name('index');
            
            Route::get('/campaigns', [AdsManagerController::class, 'campaigns'])
                ->name('campaigns');
            
            Route::get('/campaigns/{campaign}', [AdsManagerController::class, 'showCampaign'])
                ->name('campaigns.show');
            
            Route::get('/campaigns/{campaign}/adsets', [AdsManagerController::class, 'adsets'])
                ->name('adsets');
            
            Route::get('/adsets/{adset}', [AdsManagerController::class, 'showAdSet'])
                ->name('adsets.show');
            
            Route::get('/adsets/{adset}/ads', [AdsManagerController::class, 'ads'])
                ->name('ads');
            
            Route::get('/ads/{ad}', [AdsManagerController::class, 'showAd'])
                ->name('ads.show');
            
            Route::get('/creatives', [AdsManagerController::class, 'creatives'])
                ->name('creatives');
            
            Route::get('/insights', [AdsManagerController::class, 'insights'])
                ->name('insights');
        });


        /*
        |--------------------------------------------------------------------------
        | ANALYTICS & REPORTING
        |--------------------------------------------------------------------------
        */

        Route::prefix('analytics')->name('analytics.')->group(function () {
            
            Route::get('/', [AnalyticsController::class, 'index'])
                ->name('index');
            
            Route::get('/campaigns', [AnalyticsController::class, 'campaigns'])
                ->name('campaigns');
            
            Route::get('/adsets', [AnalyticsController::class, 'adsets'])
                ->name('adsets');
            
            Route::get('/ads', [AnalyticsController::class, 'ads'])
                ->name('ads');
            
            Route::get('/export', [AnalyticsController::class, 'export'])
                ->name('export');
            
            Route::get('/reports/create', [AnalyticsController::class, 'createReport'])
                ->name('reports.create');
            
            Route::post('/reports', [AnalyticsController::class, 'storeReport'])
                ->name('reports.store');
            
            Route::get('/reports/{report}', [AnalyticsController::class, 'showReport'])
                ->name('reports.show');
        });


        /*
        |--------------------------------------------------------------------------
        | SYSTEM & SETTINGS
        |--------------------------------------------------------------------------
        */

        Route::prefix('system')->name('system.')->group(function () {
            
            Route::get('/', fn() => view('admin.system.index'))->name('index');
            
            Route::get('/logs', [\App\Http\Controllers\Admin\SystemController::class, 'logs'])
                ->name('logs');
            
            Route::get('/queue', [\App\Http\Controllers\Admin\SystemController::class, 'queue'])
                ->name('queue');
            
            Route::get('/cache', [\App\Http\Controllers\Admin\SystemController::class, 'cache'])
                ->name('cache');
            
            Route::post('/cache/clear', [\App\Http\Controllers\Admin\SystemController::class, 'clearCache'])
                ->name('cache.clear');
            
            Route::get('/info', fn() => view('admin.system.info'))
                ->name('info');
        });

        Route::prefix('settings')->name('settings.')->group(function () {
            
            Route::get('/', fn() => view('admin.settings.index'))->name('index');
            
            Route::get('/general', fn() => view('admin.settings.general'))->name('general');
            
            Route::post('/general', [\App\Http\Controllers\Admin\SettingsController::class, 'updateGeneral'])
                ->name('general.update');
            
            Route::get('/meta', fn() => view('admin.settings.meta'))->name('meta');
            
            Route::post('/meta', [\App\Http\Controllers\Admin\SettingsController::class, 'updateMeta'])
                ->name('meta.update');
            
            Route::get('/team', [\App\Http\Controllers\Admin\TeamController::class, 'index'])
                ->name('team');
            
            Route::post('/team', [\App\Http\Controllers\Admin\TeamController::class, 'store'])
                ->name('team.store');
            
            Route::delete('/team/{user}', [\App\Http\Controllers\Admin\TeamController::class, 'destroy'])
                ->name('team.destroy');
        });

    });

Route::get('/admin/meta/interests', [\App\Http\Controllers\Admin\MetaTargetingController::class, 'searchInterests'])
    ->name('admin.meta.interests');

/*
|--------------------------------------------------------------------------
| PROFILE
|--------------------------------------------------------------------------
*/

    Route::get('/profile',[ProfileController::class,'edit'])->name('profile.edit');

    Route::patch('/profile',[ProfileController::class,'update'])->name('profile.update');

    Route::delete('/profile',[ProfileController::class,'destroy'])->name('profile.destroy');


Route::get(
    '/admin/creatives/{creative}/preview',
    [CreativeController::class, 'preview']
)->name('admin.creatives.preview');
Route::get('/admin/inbox/{conversation}/messages', 
    [App\Http\Controllers\Admin\InboxController::class, 'fetchMessages']
)->name('admin.inbox.fetch');
Route::get('/admin/bulk', function(){
return view('admin.bulk.index');
})->middleware('auth');

Route::post('/admin/bulk-send',
[InboxController::class,'bulkSend']
)->name('admin.bulk.send')->middleware('auth');

Route::delete('/admin/inbox/{conversation}/delete', [InboxController::class,'deleteConversation'])
    ->name('admin.inbox.delete');
    Route::get('/admin/inbox/{conversation}/messages', [InboxController::class, 'fetchMessages'])
    ->name('admin.inbox.messages');
require __DIR__.'/auth.php';
