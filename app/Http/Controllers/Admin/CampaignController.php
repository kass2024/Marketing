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

    /**
     * Display campaign list
     */
    public function index()
    {
        $campaigns = Campaign::latest()->paginate(20);

        return view('admin.campaigns.index', compact('campaigns'));
    }

    /**
     * Show create campaign form
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

    /**
     * Store campaign in Meta + Local DB
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'objective' => 'required|string',
            'daily_budget' => 'required|numeric|min:5'
        ]);

        try {

            $account = AdAccount::first();

            if (!$account) {
                return back()->withErrors([
                    'meta' => 'No ad account available.'
                ]);
            }

            Log::info('Creating Meta Campaign', [
                'account' => $account->meta_id,
                'data' => $data
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
                ]);
            }

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

            Log::error('Campaign Store Failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors([
                'meta' => 'Unable to create campaign: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Edit campaign
     */
    public function edit(Campaign $campaign)
    {
        return view('admin.campaigns.edit', compact('campaign'));
    }

    /**
     * Update campaign locally
     */
    public function update(Request $request, Campaign $campaign)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'daily_budget' => 'required|numeric|min:5',
            'status' => 'required|string'
        ]);

        $campaign->update([
            'name' => $data['name'],
            'daily_budget' => $data['daily_budget'] * 100,
            'status' => $data['status']
        ]);

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', 'Campaign updated.');
    }

    /**
     * Delete campaign locally
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
                'message' => $e->getMessage()
            ]);

            return back()->withErrors([
                'meta' => 'Unable to delete campaign.'
            ]);
        }
    }
}