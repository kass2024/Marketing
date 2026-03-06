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
    | Create Form
    |--------------------------------------------------------------------------
    */

    public function create(int $campaignId = null): View
    {
        Log::info('META_ADSET_FORM_OPENED',[
            'campaign_id'=>$campaignId
        ]);

        return view('admin.adsets.create',[
            'campaigns' => Campaign::latest()->get(),
            'selectedCampaign' => $campaignId,
            'countries' => config('meta.countries'),
            'languages' => config('meta.languages')
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store AdSet
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): RedirectResponse
    {

        Log::info('META_ADSET_STORE_REQUEST',$request->all());

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

        Log::info('META_ADSET_VALIDATED_DATA',$data);

        if ($data['age_min'] >= $data['age_max']) {

            Log::warning('META_ADSET_AGE_INVALID',$data);

            return back()->withErrors([
                'age' => 'Maximum age must be greater than minimum age.'
            ]);
        }

        DB::beginTransaction();

        try {

            Log::info('META_ADSET_DB_TRANSACTION_START');

            $campaign = Campaign::with('adAccount')->findOrFail($data['campaign_id']);

            Log::info('META_CAMPAIGN_FOUND',[
                'campaign_id'=>$campaign->id,
                'meta_id'=>$campaign->meta_id
            ]);

            if (!$campaign->meta_id) {
                throw new Exception('Campaign is not synced with Meta.');
            }

            if (!$campaign->adAccount || !$campaign->adAccount->meta_id) {
                throw new Exception('Ad account is not connected.');
            }

            /*
            |--------------------------------------------------------------------------
            | Build Targeting
            |--------------------------------------------------------------------------
            */

            $targeting = [

                'age_min' => (int)$data['age_min'],
                'age_max' => (int)$data['age_max'],

                'geo_locations' => [
                    'countries' => array_values($data['countries'])
                ]

            ];

            Log::info('META_TARGETING_BASE',$targeting);

            /*
            |--------------------------------------------------------------------------
            | Genders
            |--------------------------------------------------------------------------
            */

            if (!empty($data['genders'])) {

                $targeting['genders'] = collect($data['genders'])
                    ->map(fn($g)=>(int)$g)
                    ->values()
                    ->toArray();

                Log::info('META_TARGETING_GENDERS',$targeting['genders']);
            }

            /*
            |--------------------------------------------------------------------------
            | Languages
            |--------------------------------------------------------------------------
            */

            if (!empty($data['languages'])) {

                $targeting['locales'] = collect($data['languages'])
                    ->map(fn($l)=>(int)$l)
                    ->values()
                    ->toArray();

                Log::info('META_TARGETING_LANGUAGES',$targeting['locales']);
            }

            /*
            |--------------------------------------------------------------------------
            | Interests
            |--------------------------------------------------------------------------
            */

            if (!empty($data['interests'])) {

                $targeting['flexible_spec'] = [[

                    'interests' => collect($data['interests'])
                        ->map(fn($id)=>[
                            'id'=>(string)$id
                        ])
                        ->values()
                        ->toArray()

                ]];

                Log::info('META_TARGETING_INTERESTS',$targeting['flexible_spec']);
            }

            /*
            |--------------------------------------------------------------------------
            | Placements
            |--------------------------------------------------------------------------
            */

            if (!empty($data['publisher_platforms'])) {

                $targeting['publisher_platforms'] = array_values($data['publisher_platforms']);

                Log::info('META_TARGETING_PLATFORMS',$targeting['publisher_platforms']);
            }

            Log::info('META_TARGETING_FINAL',$targeting);

            /*
            |--------------------------------------------------------------------------
            | Build Payload
            |--------------------------------------------------------------------------
            */

            $payload = [

                'name' => $data['name'],

                'campaign_id' => $campaign->meta_id,

                'daily_budget' => (int)$data['daily_budget'] * 100,

                'billing_event' => 'IMPRESSIONS',

                'optimization_goal' => 'REACH',

                'status' => 'PAUSED',

                'targeting' => $targeting

            ];

            Log::info('META_ADSET_CREATE_PAYLOAD',[
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

            Log::info('META_API_RESPONSE',$response);

            if (empty($response['id'])) {

                Log::error('META_API_FAILED',$response);

                $errorMessage = $response['error']['message']
                    ?? 'Meta API returned unknown error';

                throw new Exception($errorMessage);
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

                'daily_budget'=>(int)$data['daily_budget'] * 100,

                'billing_event'=>'IMPRESSIONS',

                'optimization_goal'=>'REACH',

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

        }
        catch (Throwable $e) {

            DB::rollBack();

            Log::error('META_ADSET_CREATE_FAILED',[
                'error'=>$e->getMessage(),
                'trace'=>$e->getTraceAsString()
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'meta'=>$e->getMessage()
                ]);
        }
    }
}