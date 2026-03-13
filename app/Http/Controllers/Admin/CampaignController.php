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
    | Campaign List
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        try {

            $campaigns = Campaign::query()
                ->withCount('adSets')
                ->latest()
                ->paginate(20);

            return view('admin.campaigns.index', [

                'campaigns' => $campaigns,
                'totalAdSets' => AdSet::count(),

                'activeCampaigns' =>
                    Campaign::where('status','ACTIVE')->count(),

                'pausedCampaigns' =>
                    Campaign::where('status','PAUSED')->count(),

                'hasAdAccount' => AdAccount::exists()
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
                'adSets' => fn($q) => $q->latest()
            ])->findOrFail($id);

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

            /*
            |--------------------------------------------------------------------------
            | Ensure Meta Account Exists
            |--------------------------------------------------------------------------
            */

            $account = AdAccount::whereNotNull('meta_id')->first();

            if (!$account) {
                throw new \Exception('Meta ad account is not connected.');
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Names
            |--------------------------------------------------------------------------
            */

            if (Campaign::where('name',$data['name'])->exists()) {

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

            $campaign = Campaign::create([

                'ad_account_id' => $account->id,
                'meta_id' => $metaId,

                'name' => $data['name'],
                'objective' => $data['objective'],
                'status' => $data['status']

            ]);

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
            'status' => 'ACTIVE'
        ]);

        Log::info('CAMPAIGN_ACTIVATED',[
            'campaign_id'=>$campaign->id,
            'meta_id'=>$campaign->meta_id
        ]);

        return back()->with('success','Campaign activated.');

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
            'status'=>'PAUSED'
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

        $campaign->update([
            'status' => $metaCampaign['status'] ?? $campaign->status
        ]);

        Log::info('CAMPAIGN_SYNCED',[
            'campaign_id'=>$campaign->id,
            'meta_status'=>$metaCampaign['status'] ?? null
        ]);

        return back()->with('success','Campaign synced.');

    } catch(Throwable $e){

        Log::error('CAMPAIGN_SYNC_FAILED',[
            'campaign_id'=>$campaign->id,
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'meta'=>'Unable to sync campaign.'
        ]);
    }
}
}