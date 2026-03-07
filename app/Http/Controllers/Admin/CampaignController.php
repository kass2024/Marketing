<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\Campaign;
use App\Models\AdAccount;
use App\Models\AdSet;
use App\Services\MetaAdsService;

use Throwable;

class CampaignController extends Controller
{
    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | Campaign List (Optimized for production)
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        try {

            $campaigns = Campaign::query()
                ->withCount('adSets')
                ->latest()
                ->paginate(20);

            $totalAdSets = AdSet::count();

            $activeCampaigns = Campaign::where('status', 'ACTIVE')->count();
            $pausedCampaigns = Campaign::where('status', 'PAUSED')->count();

            $hasAdAccount = AdAccount::exists();

            return view('admin.campaigns.index', [
                'campaigns' => $campaigns,
                'totalAdSets' => $totalAdSets,
                'activeCampaigns' => $activeCampaigns,
                'pausedCampaigns' => $pausedCampaigns,
                'hasAdAccount' => $hasAdAccount
            ]);

        } catch (Throwable $e) {

            Log::error('CAMPAIGN_INDEX_FAILED', [
                'error' => $e->getMessage()
            ]);

            abort(500, 'Unable to load campaigns.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Show Campaign → AdSets
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        try {

            $campaign = Campaign::with([
                'adSets' => function ($query) {
                    $query->latest();
                }
            ])->findOrFail($id);

            return view('admin.campaigns.show', compact('campaign'));

        } catch (Throwable $e) {

            Log::error('CAMPAIGN_SHOW_FAILED', [
                'campaign_id' => $id,
                'error' => $e->getMessage()
            ]);

            abort(404, 'Campaign not found.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Campaign Page
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

        DB::beginTransaction();

        try {

            $account = AdAccount::first();

            if (!$account) {
                throw new \Exception('No Meta ad account connected.');
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent duplicate campaign names
            |--------------------------------------------------------------------------
            */

            if (Campaign::where('name', $data['name'])->exists()) {

                return back()->withErrors([
                    'name' => 'A campaign with this name already exists.'
                ])->withInput();
            }

            /*
            |--------------------------------------------------------------------------
            | Map Objective
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
            | Create Campaign on Meta
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

                    throw new \Exception(
                        $response['error']['message']
                        ?? 'Meta API failed to create campaign.'
                    );
                }

                $metaId = $response['id'];
            }

            /*
            |--------------------------------------------------------------------------
            | Save Campaign Locally
            |--------------------------------------------------------------------------
            */

            $campaign = Campaign::create([
                'ad_account_id' => $account->id,
                'meta_id' => $metaId,
                'name' => $data['name'],
                'objective' => $data['objective'],
                'status' => $data['status']
            ]);

            DB::commit();

            Log::info('META_CAMPAIGN_CREATED', [
                'campaign_id' => $campaign->id,
                'meta_id' => $campaign->meta_id
            ]);

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', 'Campaign created successfully.');

        } catch (Throwable $e) {

            DB::rollBack();

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

        } catch (Throwable $e) {

            Log::error('META_CAMPAIGN_DELETE_FAILED', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'meta' => 'Unable to delete campaign.'
            ]);
        }
    }
}