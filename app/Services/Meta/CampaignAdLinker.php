<?php

namespace App\Services\Meta;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Creative;
use App\Services\MetaAdsService;
use App\Support\TenantScope;
use Exception;
use Illuminate\Support\Facades\Log;

class CampaignAdLinker
{
    public function __construct(
        protected MetaAdsService $meta
    ) {}

    /**
     * Attach a synced creative to an existing ad set by creating the local + Meta ad.
     */
    public function linkCreativeToAdSet(AdSet $adset, Creative $creative, string $status = 'PAUSED'): Ad
    {
        TenantScope::assertAdSet($adset);

        if ((int) $creative->adset_id !== (int) $adset->id) {
            $creative->update(['adset_id' => $adset->id, 'campaign_id' => $adset->campaign_id]);
        }

        if (! $adset->meta_id) {
            throw new Exception('Ad set is not synced to Meta. Sync the ad set before publishing ads.');
        }

        if (! $creative->meta_id) {
            throw new Exception('Creative is not synced to Meta. Enable “Sync to Meta” when saving.');
        }

        $exists = Ad::query()
            ->where('adset_id', $adset->id)
            ->where('creative_id', $creative->id)
            ->first();

        if ($exists) {
            return $exists;
        }

        $campaign = $adset->campaign()->with('adAccount')->first();
        $account = $campaign?->adAccount ?? TenantScope::requireAdAccount();
        $accountId = str_starts_with($account->meta_id, 'act_') ? $account->meta_id : 'act_'.$account->meta_id;

        $metaAd = $this->meta->createAd($accountId, [
            'name' => $creative->name,
            'adset_id' => $adset->meta_id,
            'status' => $status,
            'creative' => ['id' => $creative->meta_id],
        ]);

        $ad = Ad::create([
            'adset_id' => $adset->id,
            'creative_id' => $creative->id,
            'name' => $creative->name,
            'status' => $status,
            'meta_ad_id' => $metaAd['id'] ?? null,
            'meta_effective_status' => $metaAd['effective_status'] ?? null,
            'daily_budget' => $adset->daily_budget,
        ]);

        Log::info('CAMPAIGN_AD_LINKED', [
            'ad_id' => $ad->id,
            'meta_ad_id' => $ad->meta_ad_id,
            'adset_id' => $adset->id,
            'creative_id' => $creative->id,
        ]);

        return $ad;
    }
}
