<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Creative;
use App\Services\InstagramDeliveryService;
use App\Services\MetaAdsService;
use App\Support\AdBudgetGuard;
use App\Models\AdAccount;
use App\Support\TenantScope;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

use Throwable;
use Exception;

class AdController extends Controller
{
    protected MetaAdsService $meta;

    protected InstagramDeliveryService $instagramDelivery;

    public function __construct(MetaAdsService $meta, InstagramDeliveryService $instagramDelivery)
    {
        $this->meta = $meta;
        $this->instagramDelivery = $instagramDelivery;
    }

    protected function resolveMetaAccountId(): ?string
    {
        return TenantScope::adAccountMetaId()
            ?? AdAccount::query()->whereNotNull('meta_id')->value('meta_id');
    }

    /**
     * Pull lifetime + today spend from Meta (same source as the insight dashboard).
     */
    /**
     * Cached Meta insights (avoids slow/time-out live polls).
     *
     * @return array{lifetime: array<string, array<string, mixed>>, today: array<string, array<string, mixed>>}
     */
    protected function metaInsightsMaps(string $accountId): array
    {
        $cacheKey = 'meta_ad_insights_maps:'.md5($accountId);

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($accountId) {
            return [
                'lifetime' => $this->meta->getAdInsightsMap($accountId, 'maximum'),
                'today' => $this->meta->getAdInsightsMap($accountId, 'today'),
            ];
        });
    }

    /**
     * @return array<string, array<string, array{impressions: int, clicks: int, spend: float}>>
     */
    protected function placementInsightsMap(string $accountId): array
    {
        $cacheKey = 'meta_ad_placement_maps:'.md5($accountId);

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($accountId) {
            return $this->meta->getAdPlacementInsightsMap($accountId, 'maximum');
        });
    }

    /**
     * @param  array<string, array<string, array{impressions: int, clicks: int, spend: float}>>  $placementMap
     */
    protected function applyPlacementDeliveryToAds(iterable $ads, array $placementMap): void
    {
        foreach ($ads as $ad) {
            $metaId = (string) ($ad->meta_ad_id ?? '');
            $delivery = $metaId !== '' ? ($placementMap[$metaId] ?? []) : [];
            $ad->setAttribute('placement_delivery', $delivery);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPlacementPayloadForAd(Ad $ad): array
    {
        $ad->loadMissing('adSet');
        $delivery = is_array($ad->getAttribute('placement_delivery'))
            ? $ad->getAttribute('placement_delivery')
            : [];

        $ig = $delivery['instagram'] ?? [];
        $fb = $delivery['facebook'] ?? [];
        $igImpressions = (int) ($ig['impressions'] ?? 0);
        $fbImpressions = (int) ($fb['impressions'] ?? 0);

        return [
            'targets' => $ad->adSet?->placementTargetLabels() ?? [],
            'targets_instagram' => $ad->adSet?->targetsInstagram() ?? false,
            'delivers_instagram' => $igImpressions > 0,
            'delivers_facebook' => $fbImpressions > 0,
            'instagram_impressions' => $igImpressions,
            'instagram_clicks' => (int) ($ig['clicks'] ?? 0),
            'facebook_impressions' => $fbImpressions,
            'facebook_clicks' => (int) ($fb['clicks'] ?? 0),
            'summary' => $this->formatPlacementSummary($ad->adSet, $delivery),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $delivery
     */
    protected function formatPlacementSummary(?AdSet $adSet, array $delivery): string
    {
        $igImpressions = (int) ($delivery['instagram']['impressions'] ?? 0);
        $fbImpressions = (int) ($delivery['facebook']['impressions'] ?? 0);

        if ($igImpressions > 0 && $fbImpressions > 0) {
            return 'Delivering on Facebook & Instagram';
        }

        if ($igImpressions > 0) {
            return 'Delivering on Instagram';
        }

        if ($fbImpressions > 0) {
            return 'Facebook only (no IG impressions yet)';
        }

        if ($adSet?->targetsInstagram()) {
            return 'IG targeted — no impressions yet';
        }

        return 'No platform data from Meta';
    }

    protected function hydratePlacementDeliveryFromMeta(iterable $ads): void
    {
        $accountId = $this->resolveMetaAccountId();

        if (! $accountId) {
            return;
        }

        try {
            $this->applyPlacementDeliveryToAds($ads, $this->placementInsightsMap($accountId));
        } catch (Throwable $e) {
            Log::warning('ADS_PLACEMENT_INSIGHTS_FAILED', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function applyMetaInsightsToAds(iterable $ads, array $lifetime, array $today, bool $enforceBudget = true): void
    {
        foreach ($ads as $ad) {
            $metaId = (string) $ad->meta_ad_id;
            $row = $lifetime[$metaId] ?? null;

            if (! $row) {
                continue;
            }

            $impressions = (int) ($row['impressions'] ?? 0);
            $clicks = (int) ($row['clicks'] ?? 0);
            $spend = (float) ($row['spend'] ?? 0);
            $ctr = $impressions > 0
                ? round(($clicks / $impressions) * 100, 2)
                : (float) ($row['ctr'] ?? 0);
            $metaTodaySpend = (float) ($today[$metaId]['spend'] ?? 0);

            $payload = [
                'impressions' => $impressions,
                'clicks' => $clicks,
                'spend' => $spend,
                'ctr' => $ctr,
            ];

            if (Schema::hasColumn('ads', 'spend_date')) {
                $payload = array_merge($payload, AdBudgetGuard::metricsPayloadFromMetaToday($ad, $metaTodaySpend));
            } else {
                $payload['daily_spend'] = AdBudgetGuard::isBudgetLimitPaused($ad)
                    ? 0
                    : AdBudgetGuard::cappedSessionSpend($ad, $metaTodaySpend);
            }

            $ad->update(AdBudgetGuard::filterPersistablePayload($payload));

            $ad->impressions = $impressions;
            $ad->clicks = $clicks;
            $ad->spend = $spend;
            $ad->ctr = $ctr;
            $ad->daily_spend = (float) ($payload['daily_spend'] ?? 0);
            $ad->spend_date = isset($payload['spend_date']) ? $payload['spend_date'] : $ad->spend_date;
            if (isset($payload['daily_spend_anchor'])) {
                $ad->daily_spend_anchor = (float) $payload['daily_spend_anchor'];
            }

            AdBudgetGuard::reconcileBudgetLimitPause($ad, $metaTodaySpend);

            if ($enforceBudget) {
                AdBudgetGuard::enforce($ad, $this->meta, $metaTodaySpend);
            }
        }
    }

    protected function hydrateLiveMetricsFromMeta(iterable $ads, bool $enforceBudget = true, bool $cacheOnly = false): bool
    {
        $ads = collect($ads)->filter(fn (Ad $ad) => $ad->meta_ad_id);

        if ($ads->isEmpty()) {
            return true;
        }

        $accountId = TenantScope::adAccountMetaId();

        if (! $accountId) {
            return true;
        }

        try {
            $cacheKey = 'meta_ad_insights_maps:'.md5($accountId);

            if ($cacheOnly) {
                $maps = Cache::get($cacheKey);

                if (! is_array($maps) || empty($maps['lifetime'])) {
                    return false;
                }
            } else {
                $maps = $this->metaInsightsMaps($accountId);
            }

            $this->applyMetaInsightsToAds(
                $ads,
                $maps['lifetime'] ?? [],
                $maps['today'] ?? [],
                $enforceBudget
            );

            return true;
        } catch (Throwable $e) {
            Log::warning('ADS_LIVE_METRICS_REFRESH_FAILED', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function adsMetricsQuery()
    {
        return TenantScope::ads(
            Ad::query()
                ->with(['adSet:id,name,campaign_id,targeting'])
                ->select($this->adsSelectColumns())
                ->latest()
        );
    }

    protected function adsListQuery()
    {
        return TenantScope::ads(
            Ad::query()
                ->with([
                    'creative:id,name,image_url,image_hash,json_payload',
                    'adSet:id,name,campaign_id,targeting',
                    'adSet.campaign:id,name,ad_account_id',
                    'adSet.campaign.adAccount:id,name,meta_id',
                ])
                ->select($this->adsSelectColumns())
                ->latest()
        );
    }

    protected function adsSelectColumns(): array
    {
        return array_values(array_filter([
            'id',
            'name',
            'adset_id',
            'creative_id',
            'meta_ad_id',
            'status',
            'impressions',
            'clicks',
            Schema::hasColumn('ads', 'ctr') ? 'ctr' : null,
            'spend',
            Schema::hasColumn('ads', 'daily_budget') ? 'daily_budget' : null,
            Schema::hasColumn('ads', 'daily_spend') ? 'daily_spend' : null,
            Schema::hasColumn('ads', 'daily_spend_anchor') ? 'daily_spend_anchor' : null,
            Schema::hasColumn('ads', 'pause_reason') ? 'pause_reason' : null,
            Schema::hasColumn('ads', 'spend_date') ? 'spend_date' : null,
            'created_at',
        ]));
    }

    protected function buildAdsMetrics(iterable $ads, ?int $totalAds = null): array
    {
        $collection = collect($ads);

        return [
            'total_ads' => $totalAds ?? $collection->count(),
            'active_ads' => $collection->where('status', 'ACTIVE')->count(),
            'total_spend' => $collection->sum('spend'),
            'total_clicks' => $collection->sum('clicks'),
            'total_impressions' => $collection->sum('impressions'),
            'avg_ctr' => $collection->avg('ctr'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | LIST ADS
    |--------------------------------------------------------------------------
    */
public function index(): View
{
    $ads = $this->adsListQuery()->paginate(20);

    $allAds = $this->adsMetricsQuery()->get();
    $this->hydrateLiveMetricsFromMeta($allAds, true, false);
    $this->hydratePlacementDeliveryFromMeta($allAds);

    $freshMap = $allAds->keyBy('id');
    $ads->setCollection(
        $ads->getCollection()->map(fn (Ad $ad) => $freshMap->get($ad->id, $ad))
    );

    try {
        Creative::hydrateMetaImageUrls(
            $ads->getCollection()->pluck('creative')->filter(),
            $this->meta
        );
    } catch (Throwable) {
        // Previews fall back to local storage URLs when Meta lookup fails.
    }

    $metrics = $this->buildAdsMetrics($allAds);

    return view('admin.ads.index', [
        'ads' => $ads,
        'metrics' => $metrics,
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | CREATE FORM
    |--------------------------------------------------------------------------
    */

    public function create(): View
    {
        $adsets = TenantScope::adSets(
            AdSet::with('campaign.adAccount')
        )->latest()->get();

        $creatives = TenantScope::creatives(Creative::query())->latest()->get();

        try {
            Creative::hydrateMetaImageUrls($creatives, $this->meta);
        } catch (Throwable) {
            // Previews fall back to local storage URLs when Meta lookup fails.
        }

        return view('admin.ads.create', compact('adsets','creatives'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE AD
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): RedirectResponse
    {
    $data = $request->validate([
'name' => 'required|string|max:255',
'adset_id' => 'required|exists:ad_sets,id',
'creative_id' => 'required|exists:creatives,id',
'daily_budget' => 'required|numeric|min:0.10',
'status' => 'required|in:ACTIVE,PAUSED'
]);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | LOAD MODELS
            |--------------------------------------------------------------------------
            */

            $adset = AdSet::with('campaign.adAccount')
                ->findOrFail($data['adset_id']);

           #$creative = Creative::where('meta_id',$data['creative_id'])->firstOrFail();
           $creative = Creative::findOrFail($data['creative_id']);

            $campaign = $adset->campaign;

            $adAccount = $campaign->adAccount ?? null;


            /*
            |--------------------------------------------------------------------------
            | VALIDATE META SYNC
            |--------------------------------------------------------------------------
            */

            if (!$adset->meta_id) {
                throw new Exception('AdSet not synced with Meta.');
            }

            if (!$creative->meta_id) {
                throw new Exception('Creative not synced with Meta.');
            }

            if (!$adAccount || !$adAccount->meta_id) {
                throw new Exception('Meta Ad Account not connected.');
            }

            $this->instagramDelivery->assertInstagramConfigured(
                $campaign->meta_page_id ?? TenantScope::pageId(),
                $adAccount->meta_id
            );

            $this->assertCreativeEligibleForMetaAd($creative, $adset);

            /*
            |--------------------------------------------------------------------------
            | PREVENT DUPLICATE ADS
            |--------------------------------------------------------------------------
            */

            $exists = Ad::where('adset_id',$adset->id)
                ->where('creative_id',$creative->id)
                ->first();

            if ($exists) {
                throw new Exception('Ad already exists for this AdSet + Creative.');
            }


            /*
            |--------------------------------------------------------------------------
            | FORMAT ACCOUNT ID
            |--------------------------------------------------------------------------
            */

            $accountId = $adAccount->meta_id;

            if (!str_starts_with($accountId,'act_')) {
                $accountId = 'act_'.$accountId;
            }


          /*
/*
|--------------------------------------------------------------------------
| META PAYLOAD
|--------------------------------------------------------------------------
| Prepare the payload to create the Ad in Meta.
| The creative meta_id is passed as "id" and converted by MetaAdsService
| to the required format: creative={"creative_id":"..."}
*/

$payload = [

    // Ad name in Meta
    'name' => $data['name'],

    // Meta AdSet ID (not local id)
    'adset_id' => $adset->meta_id,

    // Attach existing Meta creative
    'creative' => [
        'id' => $creative->meta_id
    ],

    // Delivery status (default paused for safety)
    'status' => $data['status'] ?? 'PAUSED'

];

/*
|--------------------------------------------------------------------------
| LOG META REQUEST
|--------------------------------------------------------------------------
|
| Useful for debugging API calls and verifying payload correctness.
|
*/

Log::info('META_AD_CREATE_REQUEST', [

    'account_id' => $accountId,

    'adset_meta_id' => $adset->meta_id,

    'creative_meta_id' => $creative->meta_id,

    'payload' => $payload

]);
            /*
            |--------------------------------------------------------------------------
            | CREATE AD ON META (IG-enabled creative first)
            |--------------------------------------------------------------------------
            */

            $response = $this->createMetaAdWithLinkFallbacks(
                $accountId,
                $payload,
                $creative,
                $adset
            );

            Log::info('META_AD_CREATE_RESPONSE', $response);


            if (!isset($response['id'])) {

                $error = $response['error']['message']
                    ?? 'Meta API failed creating ad';

                throw new Exception($error);
            }


            /*
            |--------------------------------------------------------------------------
            | SAVE LOCAL AD
            |--------------------------------------------------------------------------
            */
$ad = Ad::create([
'adset_id' => $adset->id,
'creative_id' => $creative->id,
'meta_ad_id' => $response['id'],

'name' => $data['name'],
'status' => $data['status'],

'daily_budget' => $request->input('daily_budget', 2),
'daily_spend' => 0
]);

            DB::commit();

            AdBudgetGuard::syncMetaAdSetBudget($ad, $this->meta);

            Log::info('META_AD_CREATED', [

                'local_ad_id' => $ad->id,

                'meta_ad_id' => $response['id']

            ]);


            return redirect()
                ->route('admin.ads.index')
                ->with('success','Ad created and synced to Meta.');

        }

        catch (Throwable $e) {

            DB::rollBack();

            Log::error('AD_CREATION_FAILED', [

                'error' => $e->getMessage()

            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'meta' => 'Ad creation failed: '.$e->getMessage()
                ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ADS BY ADSET (AJAX)
    |--------------------------------------------------------------------------
    */

    public function byAdset(int $adsetId): JsonResponse
    {
        $ads = Ad::where('adset_id',$adsetId)
            ->latest()
            ->get([
                'id',
                'name',
                'status',
                'impressions',
                'clicks',
                'spend'
            ]);

        return response()->json($ads);
    }


    /*
    |--------------------------------------------------------------------------
    | PREVIEW CREATIVE
    |--------------------------------------------------------------------------
    */
public function preview(Ad $ad): View
{
    TenantScope::assertAd($ad);

    $ad->load([
        'creative',
        'adSet',
        'adSet.campaign'
    ]);

    if ($ad->creative) {
        try {
            Creative::hydrateMetaImageUrls(collect([$ad->creative]), $this->meta);
            $ad->load('creative');
        } catch (Throwable) {
            // Preview falls back to local storage URLs when Meta lookup fails.
        }
    }

    $audience = [
        'countries' => [],
        'age' => [],
        'gender' => []
    ];

    $devices = [];
    $placements = [];

    try {

        if (!$ad->meta_ad_id) {
            throw new Exception('Ad not synced with Meta.');
        }

        /*
        |--------------------------------------------------------------------------
        | BASIC INSIGHTS
        |--------------------------------------------------------------------------
        */

        $base = $this->meta->getInsights($ad->meta_ad_id,'maximum');

        if (!empty($base)) {

            $impressions = (int) ($base['impressions'] ?? 0);
            $clicks      = (int) ($base['clicks'] ?? 0);
            $spend       = (float) ($base['spend'] ?? 0);

            $ctr = $impressions > 0
                ? round(($clicks / $impressions) * 100, 2)
                : 0;

            $ad->update([
                'impressions' => $impressions,
                'clicks'      => $clicks,
                'spend'       => $spend,
                'ctr'         => $ctr
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | COUNTRY BREAKDOWN
        |--------------------------------------------------------------------------
        */

        $countries = $this->meta->getInsights(
            $ad->meta_ad_id,
            'maximum',
            ['breakdowns' => 'country']
        );

        foreach ($countries as $row) {

            $country = $row['country'] ?? 'Unknown';

            $audience['countries'][$country] =
                ($audience['countries'][$country] ?? 0)
                + (int)($row['impressions'] ?? 0);
        }


        /*
        |--------------------------------------------------------------------------
        | AGE + GENDER BREAKDOWN
        |--------------------------------------------------------------------------
        */

        $ages = $this->meta->getInsights(
            $ad->meta_ad_id,
            'maximum',
            ['breakdowns' => 'age,gender']
        );

        foreach ($ages as $row) {

            if (!empty($row['age'])) {

                $age = $row['age'];

                $audience['age'][$age] =
                    ($audience['age'][$age] ?? 0)
                    + (int)($row['impressions'] ?? 0);
            }

            if (!empty($row['gender'])) {

                $gender = $row['gender'];

                $audience['gender'][$gender] =
                    ($audience['gender'][$gender] ?? 0)
                    + (int)($row['impressions'] ?? 0);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DEVICE BREAKDOWN
        |--------------------------------------------------------------------------
        */

        $deviceRows = $this->meta->getInsights(
            $ad->meta_ad_id,
            'maximum',
            ['breakdowns' => 'device_platform']
        );

        foreach ($deviceRows as $row) {

            $device = $row['device_platform'] ?? 'Unknown';

            $devices[$device] = [

                'device' => $device,

                'impressions' =>
                    ($devices[$device]['impressions'] ?? 0)
                    + (int)($row['impressions'] ?? 0),

                'clicks' =>
                    ($devices[$device]['clicks'] ?? 0)
                    + (int)($row['clicks'] ?? 0)
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | PLACEMENT BREAKDOWN
        |--------------------------------------------------------------------------
        */

        $placementRows = $this->meta->getInsights(
            $ad->meta_ad_id,
            'maximum',
            ['breakdowns' => 'publisher_platform']
        );

        foreach ($placementRows as $row) {

            $placement = $row['publisher_platform'] ?? 'Unknown';

            $placements[$placement] = [

                'placement' => $placement,

                'impressions' =>
                    ($placements[$placement]['impressions'] ?? 0)
                    + (int)($row['impressions'] ?? 0),

                'clicks' =>
                    ($placements[$placement]['clicks'] ?? 0)
                    + (int)($row['clicks'] ?? 0)
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | SORT DATA FOR DASHBOARD
        |--------------------------------------------------------------------------
        */

        arsort($audience['countries']);
        arsort($audience['age']);
        arsort($audience['gender']);

    }

    catch (Throwable $e) {

        Log::error('AD_INSIGHTS_PREVIEW_FAILED', [

            'ad_id' => $ad->id,
            'meta_ad_id' => $ad->meta_ad_id,
            'error' => $e->getMessage()

        ]);
    }

    return view('admin.ads.preview', [

        'ad' => $ad,

        'audience' => $audience,

        'devices' => array_values($devices),

        'placements' => array_values($placements)

    ]);
}
/*
|--------------------------------------------------------------------------
| UPDATE STATUS
|--------------------------------------------------------------------------
*/
public function updateStatus(Request $request, Ad $ad): RedirectResponse
{
    $data = $request->validate([
        'status' => 'required|in:ACTIVE,PAUSED,ARCHIVED'
    ]);

    try {

        /*
        |------------------------------------------------------------------
        | Update on Meta (if synced)
        |------------------------------------------------------------------
        */
        if ($ad->meta_ad_id) {

            $this->meta->updateAd(
                $ad->meta_ad_id,
                [
                    'status' => $data['status']
                ]
            );
        }

        /*
        |------------------------------------------------------------------
        | Determine pause reason
        |------------------------------------------------------------------
        */
        $pauseReason = $ad->pause_reason; // keep existing by default

        if ($data['status'] === 'PAUSED') {

            // Manual pause
            $pauseReason = 'manual';

        } elseif ($data['status'] === 'ACTIVE') {

            // Reset pause reason when activating
            $pauseReason = null;

        } elseif ($data['status'] === 'ARCHIVED') {

            // Archived = no pause logic needed
            $pauseReason = null;
        }

        /*
        |------------------------------------------------------------------
        | Update local DB
        |------------------------------------------------------------------
        */
        $ad->update([
            'status' => $data['status'],
            'pause_reason' => $pauseReason
        ]);

        return back()->with('success', 'Ad status updated.');

    } catch (\Throwable $e) {

        Log::error('AD_STATUS_UPDATE_FAILED', [
            'ad_id' => $ad->id,
            'status' => $data['status'] ?? null,
            'error' => $e->getMessage()
        ]);

        return back()->withErrors([
            'meta' => 'Unable to update ad status.'
        ]);
    }
}
    /*
    |--------------------------------------------------------------------------
    | DELETE AD
    |--------------------------------------------------------------------------
    */

    public function destroy(Ad $ad): RedirectResponse
    {
        try {

            if ($ad->meta_ad_id) {

                $this->meta->deleteAd($ad->meta_ad_id);

            }

            $ad->delete();

            return back()->with('success','Ad deleted.');

        }

        catch (Throwable $e) {

            Log::error('AD_DELETE_FAILED',[
                'error'=>$e->getMessage()
            ]);

            return back()->withErrors([
                'meta'=>'Unable to delete ad'
            ]);
        }
    }
    public function edit(Ad $ad): View
{
    $adsets = AdSet::with('campaign')->latest()->get();
    $creatives = Creative::latest()->get();

    return view('admin.ads.edit', [
        'ad' => $ad,
        'adsets' => $adsets,
        'creatives' => $creatives
    ]);
}
public function update(Request $request, Ad $ad): RedirectResponse
{
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'adset_id' => 'required|exists:ad_sets,id',
        'creative_id' => 'required|exists:creatives,id',
        'daily_budget' => 'required|numeric|min:1',
        'status' => 'required|in:ACTIVE,PAUSED,ARCHIVED'
    ]);

    try {

        /*
        |--------------------------------------------------------------------------
        | UPDATE META AD (only name/status allowed)
        |--------------------------------------------------------------------------
        */

        if ($ad->meta_ad_id) {

            $this->meta->updateAd(
                $ad->meta_ad_id,
                [
                    'name' => $data['name'],
                    'status' => $data['status']
                ]
            );

        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE LOCAL DB
        |--------------------------------------------------------------------------
        */

        $ad->update([
            'name' => $data['name'],
            'adset_id' => $data['adset_id'],
            'creative_id' => $data['creative_id'],
            'daily_budget' => $data['daily_budget'],
            'status' => $data['status']
        ]);

        $ad->refresh();
        AdBudgetGuard::syncMetaAdSetBudget($ad, $this->meta);

        return redirect()
            ->route('admin.ads.index')
            ->with('success','Ad updated successfully.');

    }

    catch(Throwable $e){

        Log::error('AD_UPDATE_FAILED',[
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'update'=>'Failed to update Ad'
        ]);
    }
}
public function activate(Ad $ad): RedirectResponse
{
    TenantScope::assertAd($ad);

    if (! AdBudgetGuard::canManualPublish($ad)) {
        return back()->withErrors([
            'activate' => AdBudgetGuard::publishBlockedMessage($ad),
        ]);
    }

    try {

        if ($ad->meta_ad_id) {

            $todayInsights = $this->meta->getInsights($ad->meta_ad_id, 'today');
            $metaTodaySpend = (float) ($todayInsights['spend'] ?? 0);

            AdBudgetGuard::syncMetaAdSetBudget($ad, $this->meta);
            AdBudgetGuard::beginNewSpendSession($ad, $metaTodaySpend);

            $this->meta->updateAd(
                $ad->meta_ad_id,
                ['status'=>'ACTIVE']
            );

        }

        $ad->update([
            'status'=>'ACTIVE',
            'pause_reason'=>null
        ]);

        return back()->with('success','Ad activated.');

    } catch(Throwable $e){

        Log::error('AD_ACTIVATE_FAILED',[
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'activate'=>'Failed to activate ad'
        ]);
    }
}
public function pause(Ad $ad): RedirectResponse
{
    try {

        if ($ad->meta_ad_id) {

            $this->meta->updateAd(
                $ad->meta_ad_id,
                ['status' => 'PAUSED']
            );

        }

        $ad->update([
            'status' => 'PAUSED',
            'pause_reason' => 'manual'
        ]);

        return back()->with('success','Ad paused manually.');

    } catch(Throwable $e){

        Log::error('AD_MANUAL_PAUSE_FAILED',[
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'pause'=>'Failed to pause ad'
        ]);
    }
}
public function duplicate(Ad $ad): RedirectResponse
{
    $copy = $ad->replicate();

    $copy->name = $ad->name.' Copy';

    $copy->meta_ad_id = null;

    $copy->impressions = 0;
    $copy->clicks = 0;
    $copy->spend = 0;
    $copy->ctr = 0;

    $copy->status = 'PAUSED';

    $copy->save();

    return back()->with('success','Ad duplicated.');
}
public function sync(Ad $ad): RedirectResponse
{
    TenantScope::assertAd($ad);

    if (!$ad->meta_ad_id) {
        return back()->withErrors([
            'sync' => 'Ad not synced with Meta'
        ]);
    }

    try {
        $insights = $this->meta->getInsights($ad->meta_ad_id, 'maximum');
        $today = $this->meta->getInsights($ad->meta_ad_id, 'today');

        $impressions = (int) ($insights['impressions'] ?? 0);
        $clicks = (int) ($insights['clicks'] ?? 0);
        $spend = (float) ($insights['spend'] ?? 0);
        $metaTodaySpend = (float) ($today['spend'] ?? 0);

        $ctr = $impressions > 0
            ? round(($clicks / $impressions) * 100, 2)
            : 0;

        $payload = [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'spend' => $spend,
            'ctr' => $ctr,
        ];

        if (Schema::hasColumn('ads', 'spend_date')) {
            $payload = array_merge($payload, AdBudgetGuard::metricsPayloadFromMetaToday($ad, $metaTodaySpend));
        } else {
            $payload['daily_spend'] = AdBudgetGuard::isBudgetLimitPaused($ad)
                ? 0
                : AdBudgetGuard::cappedSessionSpend($ad, $metaTodaySpend);
        }

        $ad->update(AdBudgetGuard::filterPersistablePayload($payload));

        AdBudgetGuard::enforce($ad, $this->meta, $metaTodaySpend);

        return back()->with('success','Ad metrics refreshed from Meta.');

    } catch (Throwable $e) {

        Log::error('AD_SYNC_FAILED',[
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'sync'=>$e->getMessage()
        ]);
    }
}
public function createFromAdSet(AdSet $adset): View
{
    $creatives = Creative::latest()->get();

    return view('admin.ads.create', [
        'adsets' => collect([$adset]),
        'creatives' => $creatives,
        'selectedAdSet' => $adset->id
    ]);
}
public function bulkStatusUpdate(Request $request): RedirectResponse
{
    $data = $request->validate([
        'ids' => 'required|array',
        'status' => 'required|in:ACTIVE,PAUSED'
    ]);

    Ad::whereIn('id',$data['ids'])
        ->update(['status'=>$data['status']]);

    return back()->with('success','Ads updated.');
}
/*
|--------------------------------------------------------------------------
| PUBLISH AD
|--------------------------------------------------------------------------
*/
public function publish(Ad $ad): RedirectResponse
{
    TenantScope::assertAd($ad);

    if (! AdBudgetGuard::canManualPublish($ad)) {
        return back()->withErrors([
            'publish' => AdBudgetGuard::publishBlockedMessage($ad),
        ]);
    }

    try {

        /*
        |------------------------------------------------------------------
        | Load Required Relations
        |------------------------------------------------------------------
        */
        $ad->load([
            'creative',
            'adSet.campaign.adAccount'
        ]);

        /*
        |------------------------------------------------------------------
        | Validate Required Data
        |------------------------------------------------------------------
        */
        if (!$ad->meta_ad_id) {
            throw new Exception('Ad is not synced with Meta.');
        }

        if (!$ad->adSet || !$ad->adSet->meta_id) {
            throw new Exception('AdSet not synced with Meta.');
        }

        if (!$ad->creative || !$ad->creative->meta_id) {
            throw new Exception('Creative not synced with Meta.');
        }

        /*
        |------------------------------------------------------------------
        | Prepare Payload
        |------------------------------------------------------------------
        */
        $payload = [
            'status' => 'ACTIVE'
        ];

        /*
        |------------------------------------------------------------------
        | Send Request To Meta
        |------------------------------------------------------------------
        */
        Log::info('META_AD_PUBLISH_REQUEST', [
            'ad_id' => $ad->id,
            'meta_ad_id' => $ad->meta_ad_id,
            'payload' => $payload
        ]);

        $response = $this->meta->updateAd(
            $ad->meta_ad_id,
            $payload
        );

        Log::info('META_AD_PUBLISH_RESPONSE', [
            'ad_id' => $ad->id,
            'response' => $response
        ]);

        /*
        |------------------------------------------------------------------
        | Handle Meta Errors
        |------------------------------------------------------------------
        */
        if (isset($response['error'])) {
            throw new Exception(
                $response['error']['message'] ?? 'Meta API error'
            );
        }

        $todayInsights = $this->meta->getInsights($ad->meta_ad_id, 'today');
        $metaTodaySpend = (float) ($todayInsights['spend'] ?? 0);

        AdBudgetGuard::syncMetaAdSetBudget($ad, $this->meta);
        AdBudgetGuard::beginNewSpendSession($ad, $metaTodaySpend);

        $ad->update([
            'status' => 'ACTIVE',
            'pause_reason' => null,
        ]);

        Log::info('AD_PUBLISHED_SUCCESS', [
            'ad_id' => $ad->id,
            'meta_ad_id' => $ad->meta_ad_id
        ]);

        return back()->with('success', 'Ad successfully published.');

    } catch (Throwable $e) {

        Log::error('AD_PUBLISH_FAILED', [
            'ad_id' => $ad->id ?? null,
            'meta_ad_id' => $ad->meta_ad_id ?? null,
            'error' => $e->getMessage()
        ]);

        return back()->withErrors([
            'publish' => 'Publish failed: ' . $e->getMessage()
        ]);
    }
}
public function live(): JsonResponse
{
    try {
        $ads = $this->adsMetricsQuery()->get();
        $metaSynced = $this->hydrateLiveMetricsFromMeta($ads, true, false);

        if (! $metaSynced) {
            $metaSynced = $this->hydrateLiveMetricsFromMeta($ads, true, true);
        }

        $this->hydratePlacementDeliveryFromMeta($ads);

        $metrics = $this->buildAdsMetrics($ads);

        return response()
            ->json([
                'metrics' => $metrics,
                'ads' => $ads->map(fn (Ad $ad) => $this->formatAdForLiveJson($ad))->values(),
                'refreshed_at' => now()->toIso8601String(),
                'meta_synced' => $metaSynced,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    } catch (Throwable $e) {
        Log::error('ADS_LIVE_ENDPOINT_FAILED', [
            'error' => $e->getMessage(),
        ]);

        try {
            $ads = $this->adsMetricsQuery()->get();
            $metrics = $this->buildAdsMetrics($ads);

            return response()->json([
                'metrics' => $metrics,
                'ads' => $ads->map(fn (Ad $ad) => $this->formatAdForLiveJson($ad))->values(),
                'refreshed_at' => now()->toIso8601String(),
                'meta_synced' => false,
                'warning' => 'Showing saved metrics. Meta sync will retry automatically.',
            ]);
        } catch (Throwable $fallbackError) {
            return response()->json([
                'metrics' => [
                    'total_ads' => 0,
                    'active_ads' => 0,
                    'total_spend' => 0,
                    'total_clicks' => 0,
                ],
                'ads' => [],
                'refreshed_at' => now()->toIso8601String(),
                'meta_synced' => false,
                'error' => 'Live refresh unavailable.',
            ], 500);
        }
    }
}

protected function formatAdForLiveJson(Ad $ad): array
{
    return array_merge([
        'id' => $ad->id,
        'name' => $ad->name,
        'adset_id' => $ad->adset_id,
        'creative_id' => $ad->creative_id,
        'meta_ad_id' => $ad->meta_ad_id,
        'status' => $ad->status,
        'impressions' => (int) ($ad->impressions ?? 0),
        'clicks' => (int) ($ad->clicks ?? 0),
        'ctr' => (float) ($ad->ctr ?? 0),
        'spend' => (float) ($ad->spend ?? 0),
        'daily_spend' => $ad->displayDailySpend(),
        'daily_budget' => (float) ($ad->daily_budget ?? 0),
        'pause_reason' => $ad->pause_reason ?? null,
    ], [
        'placement' => $this->buildPlacementPayloadForAd($ad),
    ]);
}

/**
 * Patch ad set placements + swap in an Instagram-enabled creative on Meta.
 */
public function enableInstagram(Ad $ad): RedirectResponse
{
    try {
        $this->instagramDelivery->repairAd($ad, true);

        return back()->with(
            'success',
            'Instagram delivery enabled for this ad. IG impressions may take a few hours to show in the Platforms column.'
        );
    } catch (Throwable $e) {
        Log::error('AD_ENABLE_INSTAGRAM_FAILED', [
            'ad_id' => $ad->id,
            'error' => $e->getMessage(),
        ]);

        return back()->withErrors([
            'enable_instagram' => $e->getMessage(),
        ]);
    }
}

/**
 * Enable Instagram on all existing campaigns (ad sets), creatives, and ads on Meta.
 */
public function enableInstagramAll(): RedirectResponse
{
    try {
        $stats = $this->instagramDelivery->repairAll();

        if ($stats['ads']['updated'] === 0 && $stats['errors'] !== []) {
            return back()->withErrors([
                'enable_instagram' => implode(' | ', array_slice($stats['errors'], 0, 3)),
            ]);
        }

        return back()->with('success', $this->instagramDelivery->summaryMessage($stats));
    } catch (Throwable $e) {
        Log::error('AD_ENABLE_INSTAGRAM_ALL_FAILED', ['error' => $e->getMessage()]);

        return back()->withErrors([
            'enable_instagram' => $e->getMessage(),
        ]);
    }
}

private function assertCreativeEligibleForMetaAd(Creative $creative, AdSet $adset): void
{
    $creative->loadMissing('campaign');
    $adset->loadMissing('campaign');

    if (! $creative->campaign || ! $adset->campaign) {
        throw new Exception('Creative and ad set must both be linked to a campaign.');
    }

    if ((int) $creative->campaign->ad_account_id !== (int) $adset->campaign->ad_account_id) {
        throw new Exception(
            'This creative belongs to a different Meta ad account than the selected ad set.'
        );
    }

    $goal = strtoupper((string) ($adset->optimization_goal ?? ''));
    $goalsRequiringHttpsWebsite = ['LINK_CLICKS', 'LANDING_PAGE_VIEWS', 'OFFSITE_CONVERSIONS'];

    if (! in_array($goal, $goalsRequiringHttpsWebsite, true)) {
        return;
    }

    $url = trim((string) ($creative->destination_url ?? ''));
    if ($url === '') {
        throw new Exception(
            'This ad set optimizes for website visits. Add a destination URL on the creative, then create the ad again.'
        );
    }
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
private function createMetaAdWithLinkFallbacks(
    string $accountId,
    array $payload,
    Creative $creative,
    AdSet $adset
): array {
    $strategies = [];
    $urls = $this->meta->landingUrlCandidates((string) ($creative->destination_url ?? ''));

    foreach ($urls as $url) {
        try {
            $newId = $this->instagramDelivery->createInstagramCreativeOnMeta($accountId, $creative, $adset, $url);
            $strategies[] = [
                'label' => 'fresh_creative:'.$url,
                'payload' => array_merge($payload, [
                    'creative' => ['id' => $newId],
                ]),
            ];
        } catch (Throwable $e) {
            Log::warning('META_CREATIVE_RECREATE_SKIPPED', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    foreach ($urls as $url) {
        $strategies[] = [
            'label' => 'inline_spec:'.$url,
            'payload' => array_merge($payload, [
                'creative' => [
                    'spec' => $this->instagramDelivery->buildInlineLinkCreativeSpec($creative, $adset, $url),
                ],
            ]),
        ];
    }

    $strategies[] = ['label' => 'creative_id', 'payload' => $payload];

    $lastError = null;

    foreach ($strategies as $strategy) {
        try {
            Log::info('META_AD_CREATE_ATTEMPT', ['strategy' => $strategy['label']]);

            return $this->meta->createAd($accountId, $strategy['payload']);
        } catch (Throwable $e) {
            $lastError = $e;
            Log::warning('META_AD_CREATE_ATTEMPT_FAILED', [
                'strategy' => $strategy['label'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    throw $lastError ?? new Exception('Meta ad creation failed.');
}
}