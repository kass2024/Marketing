<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Campaign;
use App\Models\AdAccount;
use App\Services\MetaAdsService;

class CampaignController extends Controller
{
    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | Campaign List
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $campaigns = Campaign::latest()->paginate(20);

        return view('admin.campaigns.index', compact('campaigns'));
    }

    /*
    |--------------------------------------------------------------------------
    | Create Page
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $account = AdAccount::first();

        if (!$account) {

            return redirect()
                ->route('admin.accounts.index')
                ->withErrors([
                    'meta' => 'No Meta Ad Account connected.'
                ]);
        }

        return view('admin.campaigns.create', compact('account'));
    }

    /*
    |--------------------------------------------------------------------------
    | Store Campaign
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        Log::info('META_CAMPAIGN_STORE_REQUEST', $request->all());

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'objective' => 'required|in:OUTCOME_TRAFFIC,OUTCOME_LEADS,OUTCOME_ENGAGEMENT,OUTCOME_AWARENESS,OUTCOME_SALES',
            'status' => 'required|in:PAUSED,ACTIVE',
            'sync_meta' => 'nullable'
        ]);

        try {

            $account = AdAccount::first();

            if (!$account) {
                return back()->withErrors([
                    'meta' => 'No Meta ad account connected.'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Names
            |--------------------------------------------------------------------------
            */

            if (Campaign::where('name', $data['name'])->exists()) {

                return back()->withErrors([
                    'name' => 'A campaign with this name already exists.'
                ])->withInput();
            }

            /*
            |--------------------------------------------------------------------------
            | Map UI Objective → Meta Objective (v19)
            |--------------------------------------------------------------------------
            */
$metaObjective = $data['objective'];

            Log::info('META_OBJECTIVE_MAPPED', [
                'ui_objective' => $data['objective'],
                'meta_objective' => $metaObjective
            ]);

            $metaId = null;

            /*
            |--------------------------------------------------------------------------
            | Create Campaign On Meta
            |--------------------------------------------------------------------------
            */

            if ($request->has('sync_meta')) {

                Log::info('META_CREATE_CAMPAIGN_REQUEST', [
                    'account' => $account->meta_id,
                    'name' => $data['name'],
                    'objective' => $metaObjective
                ]);

                $response = $this->meta->createCampaign(
                    $account->meta_id,
                    [
                        'name' => $data['name'],
                        'objective' => $metaObjective,
                        'status' => $data['status']
                    ]
                );

                Log::info('META_CREATE_CAMPAIGN_RESPONSE', $response);

                if (!isset($response['id'])) {

                    return back()->withErrors([
                        'meta' => $response['error']['message']
                            ?? 'Meta API failed to create campaign.'
                    ])->withInput();
                }

                $metaId = $response['id'];
            }

            /*
            |--------------------------------------------------------------------------
            | Save Locally
            |--------------------------------------------------------------------------
            */

            $campaign = Campaign::create([
                'ad_account_id' => $account->id,
                'meta_id' => $metaId,
                'name' => $data['name'],
                'objective' => $data['objective'],
                'status' => $data['status']
            ]);

            Log::info('META_CAMPAIGN_CREATED', [
                'campaign_id' => $campaign->id,
                'meta_id' => $campaign->meta_id
            ]);

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', 'Campaign created successfully.');

        } catch (\Throwable $e) {

            Log::error('META_CAMPAIGN_CREATE_FAILED', [
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'meta' => 'Unable to create campaign: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Campaign
    |--------------------------------------------------------------------------
    */

    public function destroy(Campaign $campaign)
    {
        try {

            Log::info('META_CAMPAIGN_DELETE', [
                'campaign_id' => $campaign->id
            ]);

            $campaign->delete();

            return back()->with('success', 'Campaign deleted.');

        } catch (\Throwable $e) {

            Log::error('META_CAMPAIGN_DELETE_FAILED', [
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'meta' => 'Unable to delete campaign.'
            ]);
        }
    }
}