<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetaAdsService;
use Illuminate\Support\Facades\Log;
use Exception;

class BillingController extends Controller
{
    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | META BILLING DASHBOARD
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        try {

            $billing = $this->meta->getBillingInfo(
                config('services.meta.ad_account_id')
            );

            return view('admin.settings.billing', [
                'billing' => $billing
            ]);

        } catch (Exception $e) {

            Log::error('META_BILLING_ERROR', [
                'message' => $e->getMessage()
            ]);

            return view('admin.settings.billing', [
                'billing' => null,
                'error' => $e->getMessage()
            ]);
        }
    }
}