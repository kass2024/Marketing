<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdAccount;
use App\Services\MetaAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdAccountController extends Controller
{
    /**
     * Display all ad accounts.
     */
    public function index()
    {
        $accounts = AdAccount::latest()->get();
        return view('admin.accounts.index', compact('accounts'));
    }

    /**
     * Sync ad accounts from Meta.
     */
    public function store(Request $request)
    {
        try {

            $service = new MetaAdsService();
            $response = $service->getAdAccounts();

            if (!isset($response['data'])) {
                return back()->withErrors(['meta' => 'Unable to fetch ad accounts']);
            }

            DB::beginTransaction();

            foreach ($response['data'] as $account) {

                AdAccount::updateOrCreate(
                    ['meta_id' => $account['id']],
                    [
                        'name' => $account['name'] ?? 'Unknown',
                        'currency' => $account['currency'] ?? 'CAD',
                        'timezone' => $account['timezone_name'] ?? null,
                        'status' => $account['account_status'] ?? 'UNKNOWN'
                    ]
                );
            }

            DB::commit();

            return back()->with('success', 'Ad accounts synced successfully.');

        } catch (\Exception $e) {

            DB::rollBack();
            Log::error('AdAccount Sync Failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Sync failed.']);
        }
    }

    /**
     * Delete local ad account (does NOT delete from Meta).
     */
    public function destroy(AdAccount $account)
    {
        $account->delete();
        return back()->with('success', 'Ad account removed locally.');
    }
}