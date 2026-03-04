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
                    'meta' => 'No Meta Ad Account connected. Please sync accounts first.'
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
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'objective' => 'required|in:OUTCOME_LEADS,OUTCOME_TRAFFIC,OUTCOME_ENGAGEMENT,OUTCOME_AWARENESS,OUTCOME_SALES',
            'daily_budget' => 'required|numeric|min:5'
        ]);

        try {

            $account = AdAccount::first();

            if (!$account) {

                return back()->withErrors([
                    'meta' => 'No ad account connected.'
                ]);
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
            | Create campaign in Meta
            |--------------------------------------------------------------------------
            */

            Log::info('Creating Meta Campaign', [
                'ad_account' => $account->meta_id,
                'payload' => $data
            ]);

            $response = $this->meta->createCampaign(
                $account->meta_id,
                [
                    'name' => $data['name'],
                    'objective' => $data['objective'],
                    'daily_budget' => $data['daily_budget'] * 100,
                    'status' => 'PAUSED'
                ]
            );

            if (!isset($response['id'])) {

                Log::error('Meta Campaign Creation Failed', [
                    'response' => $response
                ]);

                return back()->withErrors([
                    'meta' => $response['error']['message'] ?? 'Meta API failed to create campaign.'
                ])->withInput();
            }

            /*
            |--------------------------------------------------------------------------
            | Save locally
            |--------------------------------------------------------------------------
            */

            $campaign = Campaign::create([
                'ad_account_id' => $account->id,
                'meta_id' => $response['id'],
                'name' => $data['name'],
                'objective' => $data['objective'],
                'daily_budget' => $data['daily_budget'] * 100,
                'status' => 'PAUSED'
            ]);

            Log::info('Campaign Created', [
                'campaign_id' => $campaign->id,
                'meta_id' => $campaign->meta_id
            ]);

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', 'Campaign created successfully.');

        } catch (\Throwable $e) {

            Log::error('Campaign Creation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors([
                'meta' => 'Unable to create campaign: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Edit Campaign
    |--------------------------------------------------------------------------
    */

    public function edit(Campaign $campaign)
    {
        return view('admin.campaigns.edit', compact('campaign'));
    }

    /*
    |--------------------------------------------------------------------------
    | Update Campaign
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, Campaign $campaign)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'daily_budget' => 'required|numeric|min:5',
            'status' => 'required|in:PAUSED,ACTIVE'
        ]);

        try {

            /*
            |--------------------------------------------------------------------------
            | Update locally
            |--------------------------------------------------------------------------
            */

            $campaign->update([
                'name' => $data['name'],
                'daily_budget' => $data['daily_budget'] * 100,
                'status' => $data['status']
            ]);

            /*
            |--------------------------------------------------------------------------
            | Sync status with Meta
            |--------------------------------------------------------------------------
            */

            if ($campaign->meta_id) {

                if ($data['status'] === 'ACTIVE') {

                    $this->meta->activateCampaign($campaign->meta_id);

                } else {

                    $this->meta->pauseCampaign($campaign->meta_id);
                }
            }

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', 'Campaign updated successfully.');

        } catch (\Throwable $e) {

            Log::error('Campaign Update Failed', [
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'meta' => 'Unable to update campaign.'
            ]);
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

            Log::info('Deleting Campaign', [
                'campaign_id' => $campaign->id,
                'meta_id' => $campaign->meta_id
            ]);

            $campaign->delete();

            return back()->with('success', 'Campaign removed locally.');

        } catch (\Throwable $e) {

            Log::error('Campaign Delete Failed', [
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'meta' => 'Unable to delete campaign.'
            ]);
        }
    }
}