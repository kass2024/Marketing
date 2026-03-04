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
    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /**
     * Display all local ad accounts.
     */
    public function index()
    {
        $accounts = AdAccount::latest()->paginate(20);

        return view('admin.accounts.index', compact('accounts'));
    }

    /**
     * Sync ad accounts from Meta Marketing API.
     */
    public function store(Request $request)
    {
        try {

            $response = $this->meta->getAdAccounts();

            if (empty($response['data'])) {
                return back()->withErrors([
                    'meta' => 'Meta returned no ad accounts. Please verify permissions.'
                ]);
            }

            DB::transaction(function () use ($response) {

                foreach ($response['data'] as $account) {

                    AdAccount::updateOrCreate(
                        ['meta_id' => $account['id']],
                        [
                            'name'     => $account['name'] ?? 'Unknown',
                            'currency' => $account['currency'] ?? null,
                            'status'   => $account['account_status'] ?? null,
                        ]
                    );
                }
            });

            return back()->with('success', 'Ad accounts synced successfully.');

        } catch (\Throwable $e) {

            Log::error('Meta AdAccount Sync Failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'meta' => 'Meta sync failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Remove local ad account (does NOT affect Meta).
     */
    public function destroy(AdAccount $account)
    {
        try {
            $account->delete();

            return back()->with('success', 'Ad account removed locally.');

        } catch (\Throwable $e) {

            Log::error('AdAccount Delete Failed', [
                'message' => $e->getMessage()
            ]);

            return back()->withErrors([
                'meta' => 'Unable to delete ad account.'
            ]);
        }
    }
}