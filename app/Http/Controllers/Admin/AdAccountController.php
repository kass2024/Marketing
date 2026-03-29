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
        Log::info('Meta AdAccount Sync Started');

        try {

            $response = $this->meta->getAdAccounts();

            Log::info('Meta Raw API Response', $response);

            if (empty($response['data'])) {

                Log::warning('Meta returned empty ad accounts list');

                return back()->withErrors([
                    'meta' => 'Meta returned no ad accounts. Please verify permissions.'
                ]);
            }

            DB::transaction(function () use ($response) {

                foreach ($response['data'] as $account) {

                    Log::info('Processing Meta Account', $account);

                    $metaId = $account['id'] ?? null;

                    if (!$metaId) {
                        Log::warning('Meta account missing ID', $account);
                        continue;
                    }

                    $statusMap = [
                        1 => 'ACTIVE',
                        2 => 'DISABLED',
                        3 => 'UNSETTLED',
                        7 => 'PENDING'
                    ];

                    $statusCode = $account['account_status'] ?? null;
                    $statusText = $statusMap[$statusCode] ?? 'UNKNOWN';

                    Log::info('Meta Status Mapping', [
                        'meta_id' => $metaId,
                        'status_code' => $statusCode,
                        'status_text' => $statusText
                    ]);

                    $record = AdAccount::updateOrCreate(
                        ['meta_id' => $metaId],
                        [
                            'ad_account_id' => $metaId,
                            'name' => AdAccount::normalizeSyncedName($account['name'] ?? null),
                            'currency' => $account['currency'] ?? null,
                            'account_status' => $statusText
                        ]
                    );

                    Log::info('Account Stored In Database', [
                        'id' => $record->id,
                        'meta_id' => $record->meta_id,
                        'status' => $record->account_status
                    ]);
                }
            });

            Log::info('Meta AdAccount Sync Completed Successfully');

            return back()->with('success', 'Ad accounts synced successfully.');

        } catch (\Throwable $e) {

            Log::error('Meta AdAccount Sync Failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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

            Log::info('Deleting Local Ad Account', [
                'id' => $account->id,
                'meta_id' => $account->meta_id
            ]);

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