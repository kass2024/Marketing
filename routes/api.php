<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhooks\MetaWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are stateless and use the "api" middleware group.
| No sessions, no CSRF, no cookies.
|
*/

/*
|--------------------------------------------------------------------------
| Health Check (Production Monitoring)
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'app' => config('app.name'),
        'env' => config('app.env'),
    ]);
});


/*
|--------------------------------------------------------------------------
| WhatsApp Webhook (Meta Cloud API)
|--------------------------------------------------------------------------
|
| IMPORTANT:
| - Must be publicly accessible
| - Must return 200 within 10 seconds
| - Must NOT use auth middleware
|
*/

Route::prefix('webhook')->group(function () {

    // Webhook verification (Meta GET request)
    Route::get('/meta', [MetaWebhookController::class, 'verify'])
        ->name('webhook.meta.verify');

    // Incoming events (Meta POST request)
    Route::post('/meta', [MetaWebhookController::class, 'handle'])
        ->name('webhook.meta.handle');

});


/*
|--------------------------------------------------------------------------
| Protected API Routes (Optional Future Expansion)
|--------------------------------------------------------------------------
|
| For internal API usage (dashboard, admin, etc.)
|
*/

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });

});