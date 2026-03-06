<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

use App\Models\Campaign;
use App\Models\AdSet;
use App\Services\MetaAdsService;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Throwable;
use Exception;

class AdSetController extends Controller
{
    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | List AdSets by Campaign
    |--------------------------------------------------------------------------
    */

    public function indexByCampaign(int $campaignId): View
    {
        $campaign = Campaign::findOrFail($campaignId);

        $adsets = AdSet::where('campaign_id',$campaignId)
            ->latest()
            ->paginate(20);

        return view('admin.adsets.index', compact('campaign','adsets'));
    }

    /*
    |--------------------------------------------------------------------------
    | Create Form
    |--------------------------------------------------------------------------
    */

    public function create(int $campaignId = null): View
    {
        $campaigns = Campaign::latest()->get();

        $selectedCampaign = $campaignId;

        $countries = config('meta.countries');

        $languages = config('meta.languages');

        return view('admin.adsets.create', compact(
            'campaigns',
            'selectedCampaign',
            'countries',
            'languages'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Store AdSet (Meta Production)
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required','exists:campaigns,id'],
            'name' => ['required','string','max:255'],
            'daily_budget' => ['required','numeric','min:5'],
            'age_min' => ['required','integer','min:18','max:65'],
            'age_max' => ['required','integer','min:18','max:65'],
            'countries' => ['required','array','min:1'],
            'genders' => ['nullable','array'],
            'languages' => ['nullable','array'],
            'interests' => ['nullable','array'],
            'publisher_platforms' => ['nullable','array']
        ]);

        if ($data['age_min'] >= $data['age_max']) {
            return back()->withErrors([
                'age' => 'Max age must be greater than min age'
            ]);
        }

        DB::beginTransaction();

        try {

            $campaign = Campaign::with('adAccount')
                ->findOrFail($data['campaign_id']);

            if (!$campaign->meta_id) {
                throw new Exception('Campaign is not synced with Meta.');
            }

            if (!$campaign->adAccount || !$campaign->adAccount->meta_id) {
                throw new Exception('Ad Account not connected.');
            }

            /*
            |--------------------------------------------------------------------------
            | Build Targeting Payload
            |--------------------------------------------------------------------------
            */

            $targeting = [
                'age_min' => $data['age_min'],
                'age_max' => $data['age_max'],
                'geo_locations' => [
                    'countries' => $data['countries']
                ]
            ];

            if (!empty($data['genders'])) {
                $targeting['genders'] = $data['genders'];
            }

            if (!empty($data['languages'])) {
                $targeting['locales'] = $data['languages'];
            }

            if (!empty($data['publisher_platforms'])) {
                $targeting['publisher_platforms'] = $data['publisher_platforms'];
            }

            if (!empty($data['interests'])) {

                $targeting['interests'] = collect($data['interests'])
                    ->map(fn($id)=>['id'=>$id])
                    ->values()
                    ->toArray();
            }

            /*
            |--------------------------------------------------------------------------
            | Meta Payload
            |--------------------------------------------------------------------------
            */

            $payload = [
                'name' => $data['name'],
                'campaign_id' => $campaign->meta_id,
                'daily_budget' => $data['daily_budget'] * 100,
                'billing_event' => 'IMPRESSIONS',
                'optimization_goal' => 'REACH',
                'targeting' => $targeting,
                'status' => 'PAUSED'
            ];

            Log::info('META_ADSET_CREATE_REQUEST',[
                'account_id'=>$campaign->adAccount->meta_id,
                'payload'=>$payload
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create On Meta
            |--------------------------------------------------------------------------
            */

            $response = $this->meta->createAdSet(
                $campaign->adAccount->meta_id,
                $payload
            );

            if(empty($response['id'])){
                throw new Exception(
                    $response['error']['message'] ?? 'Meta API failed'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Save Local
            |--------------------------------------------------------------------------
            */

            $adset = AdSet::create([
                'campaign_id'=>$campaign->id,
                'meta_id'=>$response['id'],
                'name'=>$data['name'],
                'daily_budget'=>$data['daily_budget'] * 100,
                'optimization_goal'=>'REACH',
                'billing_event'=>'IMPRESSIONS',
                'targeting'=>$targeting,
                'status'=>'PAUSED'
            ]);

            DB::commit();

            Log::info('META_ADSET_CREATED',[
                'meta_id'=>$response['id'],
                'local_id'=>$adset->id
            ]);

            return redirect()
                ->route('admin.campaigns.show',$campaign->id)
                ->with('success','AdSet created successfully.');

        } catch (Throwable $e) {

            DB::rollBack();

            Log::error('META_ADSET_CREATE_FAILED',[
                'error'=>$e->getMessage(),
                'trace'=>$e->getTraceAsString()
            ]);

            return back()
                ->withInput()
                ->withErrors(['meta'=>$e->getMessage()]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Activate
    |--------------------------------------------------------------------------
    */

    public function activate(AdSet $adset): RedirectResponse
    {
        try {

            if($adset->meta_id){

                $this->meta->updateAdSet($adset->meta_id,[
                    'status'=>'ACTIVE'
                ]);

            }

            $adset->update(['status'=>'ACTIVE']);

            return back()->with('success','AdSet activated');

        } catch(Throwable $e){

            Log::error('ADSET_ACTIVATE_FAILED',[
                'error'=>$e->getMessage()
            ]);

            return back()->withErrors(['meta'=>$e->getMessage()]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Pause
    |--------------------------------------------------------------------------
    */

    public function pause(AdSet $adset): RedirectResponse
    {
        try {

            if($adset->meta_id){

                $this->meta->updateAdSet($adset->meta_id,[
                    'status'=>'PAUSED'
                ]);

            }

            $adset->update(['status'=>'PAUSED']);

            return back()->with('success','AdSet paused');

        } catch(Throwable $e){

            Log::error('ADSET_PAUSE_FAILED',[
                'error'=>$e->getMessage()
            ]);

            return back()->withErrors(['meta'=>$e->getMessage()]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate
    |--------------------------------------------------------------------------
    */

    public function duplicate(AdSet $adset): RedirectResponse
    {
        $copy = $adset->replicate();

        $copy->name = $adset->name.' Copy';

        $copy->meta_id = null;

        $copy->status = 'PAUSED';

        $copy->save();

        return back()->with('success','AdSet duplicated');
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk Status Update
    |--------------------------------------------------------------------------
    */

    public function bulkStatusUpdate(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'=>'required|array',
            'status'=>'required|in:ACTIVE,PAUSED'
        ]);

        AdSet::whereIn('id',$request->ids)
            ->update(['status'=>$request->status]);

        return back()->with('success','AdSets updated');
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk Destroy
    |--------------------------------------------------------------------------
    */

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'=>'required|array'
        ]);

        AdSet::whereIn('id',$request->ids)->delete();

        return back()->with('success','AdSets deleted');
    }
}