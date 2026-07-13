<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\AdAccount;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Services\MetaAdsService;
use App\Support\TenantScope;

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
    | Campaign List
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        try {

            $campaignQuery = TenantScope::campaigns(Campaign::query());

            $campaigns = (clone $campaignQuery)
                ->withCount('adSets')
                ->latest()
                ->paginate(20);

            return view('admin.campaigns.index', [

                'campaigns' => $campaigns,
                'totalAdSets' => TenantScope::adSets(AdSet::query())->count(),

                'activeCampaigns' =>
                    (clone $campaignQuery)->whereIn('status', ['active', 'ACTIVE'])->count(),

                'pausedCampaigns' =>
                    (clone $campaignQuery)->whereIn('status', ['paused', 'PAUSED'])->count(),

                'hasAdAccount' => TenantScope::resolveAdAccount() !== null,
            ]);

        } catch (Throwable $e) {

            Log::error('CAMPAIGN_INDEX_FAILED', [
                'error' => $e->getMessage()
            ]);

            abort(500,'Unable to load campaigns.');
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
                'adsets' => fn ($q) => $q->withCount(['ads', 'creatives'])->latest(),
            ])->findOrFail($id);

            TenantScope::assertCampaign($campaign);

            return view('admin.campaigns.show', compact('campaign'));

        } catch (Throwable $e) {

            Log::error('CAMPAIGN_SHOW_FAILED', [
                'campaign_id' => $id,
                'error' => $e->getMessage()
            ]);

            abort(404,'Campaign not found.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Campaign Page
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        try {
            $account = TenantScope::requireAdAccount();
        } catch (\Throwable) {
            return redirect()
                ->route('admin.campaigns.index')
                ->withErrors([
                    'meta' => TenantScope::isScoped()
                        ? 'Platform Meta ad account is not configured. Contact support.'
                        : 'No Meta ad account is connected.',
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

            /*
            |--------------------------------------------------------------------------
            | Ensure Meta Account Exists
            |--------------------------------------------------------------------------
            */

            $account = TenantScope::requireAdAccount();

            $nameQuery = TenantScope::campaigns(Campaign::query());

            if ($nameQuery->where('name',$data['name'])->exists()) {

                return back()->withErrors([
                    'name' => 'Campaign name already exists.'
                ])->withInput();
            }

            /*
            |--------------------------------------------------------------------------
            | Prepare Meta Account ID
            |--------------------------------------------------------------------------
            */

            $metaAccountId = $account->meta_id;

            if (!str_starts_with($metaAccountId,'act_')) {
                $metaAccountId = 'act_'.$metaAccountId;
            }

            /*
            |--------------------------------------------------------------------------
            | Map Objective
            |--------------------------------------------------------------------------
            */

            $metaObjective = $data['objective'];

            Log::info('META_OBJECTIVE_MAPPED',[
                'objective'=>$metaObjective
            ]);

            $metaId = null;

            /*
            |--------------------------------------------------------------------------
            | Create Campaign on Meta
            |--------------------------------------------------------------------------
            */

            if ($request->has('sync_meta')) {

                Log::info('META_CREATE_CAMPAIGN_REQUEST',[
                    'account'=>$metaAccountId,
                    'name'=>$data['name'],
                    'objective'=>$metaObjective,
                    'status'=>$data['status']
                ]);

                $response = $this->meta->createCampaign(
                    $metaAccountId,
                    [
                        'name'=>$data['name'],
                        'objective'=>$metaObjective,
                        'status'=>$data['status'],
                        'special_ad_categories'=>[]
                    ]
                );

                Log::info('META_CREATE_CAMPAIGN_RESPONSE',$response);

                if (!is_array($response) || empty($response['id'])) {

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

            $campaign = Campaign::create(array_merge([

                'ad_account_id' => $account->id,
                'meta_id' => $metaId,

                'name' => $data['name'],
                'objective' => $data['objective'],
                'status' => $data['status']

            ], TenantScope::campaignAttributes()));

            DB::commit();

            Log::info('META_CAMPAIGN_CREATED',[
                'campaign_id'=>$campaign->id,
                'meta_id'=>$campaign->meta_id
            ]);

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success','Campaign created successfully.');

        } catch (Throwable $e) {

            DB::rollBack();

            Log::error('META_CAMPAIGN_CREATE_FAILED',[
                'error'=>$e->getMessage()
            ]);

            return back()->withErrors([
                'meta'=>'Unable to create campaign: '.$e->getMessage()
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
        TenantScope::assertCampaign($campaign);

        return view('admin.campaigns.edit', [
            'campaign' => $campaign,
        ]);
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
            'objective' => 'required|in:OUTCOME_TRAFFIC,OUTCOME_LEADS,OUTCOME_ENGAGEMENT,OUTCOME_AWARENESS,OUTCOME_SALES',
            'daily_budget' => 'nullable|numeric|min:0',
            'status' => 'required|in:DRAFT,ACTIVE,PAUSED,COMPLETED,draft,active,paused,completed',
        ]);

        try {
            $status = strtoupper($data['status']);

            $campaign->update([
                'name' => $data['name'],
                'objective' => $data['objective'],
                'daily_budget' => isset($data['daily_budget'])
                    ? (int) round(((float) $data['daily_budget']) * 100)
                    : $campaign->daily_budget,
                'status' => $status,
            ]);

            if ($campaign->meta_id) {
                $this->meta->updateCampaign($campaign->meta_id, [
                    'name' => $data['name'],
                    'status' => $status,
                ]);
            }

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', 'Campaign updated successfully.');

        } catch (Throwable $e) {
            Log::error('CAMPAIGN_UPDATE_FAILED', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'meta' => 'Unable to update campaign: '.$e->getMessage(),
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

            Log::info('META_CAMPAIGN_DELETE',[
                'campaign_id'=>$campaign->id,
                'meta_id'=>$campaign->meta_id
            ]);

            $campaign->delete();

            return back()->with('success','Campaign deleted.');

        } catch (Throwable $e) {

            Log::error('META_CAMPAIGN_DELETE_FAILED',[
                'campaign_id'=>$campaign->id,
                'error'=>$e->getMessage()
            ]);

            return back()->withErrors([
                'meta'=>'Unable to delete campaign.'
            ]);
        }
    }
    /*
|--------------------------------------------------------------------------
| ACTIVATE CAMPAIGN
|--------------------------------------------------------------------------
*/

public function activate(Campaign $campaign)
{
    try {

        if($campaign->meta_id){

            $this->meta->updateCampaign(
                $campaign->meta_id,
                ['status'=>'ACTIVE']
            );
        }

        $campaign->update([
            'status' => Campaign::STATUS_ACTIVE,
            'meta_effective_status' => 'ACTIVE',
        ]);

        // Activate child ad sets + ads on Meta when present
        foreach ($campaign->adsets as $adSet) {
            if ($adSet->meta_id) {
                try {
                    $this->meta->updateAdSet($adSet->meta_id, ['status' => 'ACTIVE']);
                } catch (Throwable) {
                }
            }
            $adSet->update(['status' => 'ACTIVE']);
            foreach ($adSet->ads as $ad) {
                if ($ad->meta_ad_id || $ad->meta_id) {
                    try {
                        $this->meta->updateAd($ad->meta_ad_id ?: $ad->meta_id, ['status' => 'ACTIVE']);
                    } catch (Throwable) {
                    }
                }
                $ad->update(['status' => 'ACTIVE', 'meta_effective_status' => 'ACTIVE']);
            }
        }

        Log::info('CAMPAIGN_ACTIVATED',[
            'campaign_id'=>$campaign->id,
            'meta_id'=>$campaign->meta_id
        ]);

        return back()->with('success','Campaign activated and ready to deliver on Meta.');

    } catch(Throwable $e){

        Log::error('CAMPAIGN_ACTIVATE_FAILED',[
            'campaign_id'=>$campaign->id,
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'meta'=>'Unable to activate campaign.'
        ]);
    }
}
/*
|--------------------------------------------------------------------------
| PAUSE CAMPAIGN
|--------------------------------------------------------------------------
*/

public function pause(Campaign $campaign)
{
    try {

        if($campaign->meta_id){

            $this->meta->updateCampaign(
                $campaign->meta_id,
                ['status'=>'PAUSED']
            );
        }

        $campaign->update([
            'status'=>Campaign::STATUS_PAUSED,
            'meta_effective_status' => 'PAUSED',
        ]);

        Log::info('CAMPAIGN_PAUSED',[
            'campaign_id'=>$campaign->id
        ]);

        return back()->with('success','Campaign paused.');

    } catch(Throwable $e){

        Log::error('CAMPAIGN_PAUSE_FAILED',[
            'campaign_id'=>$campaign->id,
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'meta'=>'Unable to pause campaign.'
        ]);
    }
}
/*
|--------------------------------------------------------------------------
| SYNC CAMPAIGN FROM META
|--------------------------------------------------------------------------
*/

public function sync(Campaign $campaign)
{
    try {

        if(!$campaign->meta_id){
            return back()->withErrors([
                'meta'=>'Campaign not connected to Meta.'
            ]);
        }

        $metaCampaign = $this->meta->getCampaign(
            $campaign->meta_id
        );

        $effective = $metaCampaign['effective_status'] ?? $metaCampaign['status'] ?? null;
        $campaign->update([
            'status' => Campaign::normalizeStatus($effective ?? $metaCampaign['status'] ?? $campaign->status),
            'meta_effective_status' => $effective,
            'name' => $metaCampaign['name'] ?? $campaign->name,
        ]);

        Log::info('CAMPAIGN_SYNCED',[
            'campaign_id'=>$campaign->id,
            'meta_status'=>$metaCampaign['status'] ?? null,
            'effective_status'=>$effective,
        ]);

        return back()->with('success','Campaign synced from Meta — delivery status updated.');

    } catch(Throwable $e){

        Log::error('CAMPAIGN_SYNC_FAILED',[
            'campaign_id'=>$campaign->id,
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'meta' => 'Unable to sync campaign from Meta: '.$e->getMessage(),
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| SYNC ALL CAMPAIGNS FROM META
|--------------------------------------------------------------------------
*/

public function syncAll()
{
    try {
        $campaignExit = \Illuminate\Support\Facades\Artisan::call('meta:sync-campaigns');
        $campaignOut = trim(\Illuminate\Support\Facades\Artisan::output());

        if ($campaignExit !== 0) {
            return back()->withErrors([
                'meta' => $campaignOut !== '' ? $campaignOut : 'Unable to sync campaigns from Meta.',
            ]);
        }

        // Also pull ad sets / ads when the fuller sync command is available
        try {
            \Illuminate\Support\Facades\Artisan::call('meta:sync-ads');
        } catch (Throwable) {
            // optional — campaigns sync alone is enough for delivery badges
        }

        return back()->with('success', 'All campaigns synced from Meta. Delivery statuses are up to date.');
    } catch (Throwable $e) {
        Log::error('CAMPAIGN_SYNC_ALL_FAILED', ['error' => $e->getMessage()]);

        return back()->withErrors([
            'meta' => 'Unable to sync campaigns from Meta: '.$e->getMessage(),
        ]);
    }
}
}