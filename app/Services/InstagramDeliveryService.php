<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\Creative;
use App\Support\TenantScope;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstagramDeliveryService
{
    public function __construct(
        protected MetaAdsService $meta,
    ) {}

    /**
     * @return array{
     *     campaigns: int,
     *     adsets: array{updated: int, skipped: int, failed: int},
     *     creatives: array{updated: int, skipped: int, failed: int},
     *     ads: array{updated: int, skipped: int, failed: int},
     *     errors: list<string>
     * }
     */
    public function repairAll(): array
    {
        $this->assertInstagramConfigured();

        $stats = [
            'campaigns' => $this->campaignsQuery()->whereNotNull('meta_id')->count(),
            'adsets' => ['updated' => 0, 'skipped' => 0, 'failed' => 0],
            'creatives' => ['updated' => 0, 'skipped' => 0, 'failed' => 0],
            'ads' => ['updated' => 0, 'skipped' => 0, 'failed' => 0],
            'errors' => [],
        ];

        $this->adSetsQuery()
            ->whereNotNull('meta_id')
            ->with('campaign.adAccount')
            ->orderBy('id')
            ->each(function (AdSet $adSet) use (&$stats) {
                try {
                    if ($this->repairAdSet($adSet)) {
                        $stats['adsets']['updated']++;
                    } else {
                        $stats['adsets']['skipped']++;
                    }
                } catch (Throwable $e) {
                    $stats['adsets']['failed']++;
                    $stats['errors'][] = 'Ad set '.$adSet->name.': '.$e->getMessage();
                    Log::warning('IG_REPAIR_ADSET_FAILED', ['adset_id' => $adSet->id, 'error' => $e->getMessage()]);
                }
            });

        $this->creativesQuery()
            ->whereNotNull('meta_id')
            ->with(['adset.campaign.adAccount', 'campaign.adAccount'])
            ->orderBy('id')
            ->each(function (Creative $creative) use (&$stats) {
                try {
                    if ($this->repairCreative($creative, true)) {
                        $stats['creatives']['updated']++;
                    } else {
                        $stats['creatives']['skipped']++;
                    }
                } catch (Throwable $e) {
                    $stats['creatives']['failed']++;
                    $stats['errors'][] = 'Creative '.$creative->name.': '.$e->getMessage();
                    Log::warning('IG_REPAIR_CREATIVE_FAILED', ['creative_id' => $creative->id, 'error' => $e->getMessage()]);
                }
            });

        $this->adsQuery()
            ->whereNotNull('meta_ad_id')
            ->with(['creative', 'adSet.campaign.adAccount'])
            ->orderBy('id')
            ->each(function (Ad $ad) use (&$stats) {
                try {
                    if ($this->repairAd($ad, false)) {
                        $stats['ads']['updated']++;
                    } else {
                        $stats['ads']['skipped']++;
                    }
                } catch (Throwable $e) {
                    $stats['ads']['failed']++;
                    $stats['errors'][] = 'Ad '.$ad->name.': '.$e->getMessage();
                    Log::warning('IG_REPAIR_AD_FAILED', ['ad_id' => $ad->id, 'error' => $e->getMessage()]);
                }
            });

        return $stats;
    }

    public function summaryMessage(array $stats): string
    {
        $parts = [
            $stats['campaigns'].' campaign(s) in account',
            $stats['adsets']['updated'].' ad set(s) updated on Meta',
            $stats['creatives']['updated'].' creative(s) rebuilt with Instagram',
            $stats['ads']['updated'].' ad(s) linked to IG creatives',
        ];

        $failed = $stats['adsets']['failed'] + $stats['creatives']['failed'] + $stats['ads']['failed'];
        if ($failed > 0) {
            $parts[] = $failed.' item(s) failed';
        }

        return implode('. ', $parts).'. IG impressions may take a few hours to appear.';
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(): array
    {
        $diagnosis = $this->meta->diagnoseInstagramConnection();

        return array_merge($diagnosis, [
            'entities' => [
                'campaigns' => $this->campaignsQuery()->whereNotNull('meta_id')->count(),
                'adsets' => $this->adSetsQuery()->whereNotNull('meta_id')->count(),
                'creatives' => $this->creativesQuery()->whereNotNull('meta_id')->count(),
                'ads' => $this->adsQuery()->whereNotNull('meta_ad_id')->count(),
            ],
            'new_ads' => [
                'adset_placements' => 'Facebook + Instagram on create (automatic & manual)',
                'creative' => 'instagram_user_id on Meta creative create',
                'ad' => 'IG-enabled creative preferred on create',
            ],
        ]);
    }

    /**
     * @throws Exception
     */
    public function assertInstagramConfigured(?string $pageId = null, ?string $accountId = null): void
    {
        if ($this->meta->resolveInstagramUserId($pageId, $accountId) === null) {
            $diag = $this->meta->diagnoseInstagramConnection($pageId, $accountId);
            $page = $diag['page_id'] ?: '(META_PAGE_ID not set)';

            throw new Exception(
                'No Instagram account found for WABA Facebook Page '.$page.' (ad account '.$diag['ad_account_id'].'). '
                .'Link that Page to Instagram in Meta Business Suite, or set META_INSTAGRAM_USER_ID in WABA .env only. '
                .'Do not reuse xanderbot credentials. Run: php artisan meta:verify-instagram'
            );
        }
    }

    public function repairAdSet(AdSet $adSet): bool
    {
        if (empty($adSet->meta_id)) {
            return false;
        }

        $changed = $this->meta->ensureAdSetTargetsInstagram((string) $adSet->meta_id);

        $localTargeting = is_array($adSet->targeting) ? $adSet->targeting : [];
        $patched = $this->meta->targetingWithFacebookAndInstagram($localTargeting);
        $adSet->update(['targeting' => $patched]);

        return $changed || $this->adSetMissingInstagram($localTargeting);
    }

    public function repairCreative(Creative $creative, bool $force = false): bool
    {
        if (empty($creative->meta_id)) {
            return false;
        }

        $adSet = $this->resolveAdSetForCreative($creative);
        $accountId = $this->resolveAccountIdForCreative($creative, $adSet);

        if ($accountId === null) {
            throw new Exception('No Meta ad account for this creative.');
        }

        $stored = is_array($creative->json_payload) ? $creative->json_payload : [];
        $spec = is_array($stored['object_story_spec'] ?? null) ? $stored['object_story_spec'] : [];
        if (! $force && ! empty($spec['instagram_user_id'])) {
            return false;
        }

        $url = $this->meta->normalizeLandingUrlForMeta(
            (string) ($creative->destination_url ?? ''),
            false
        );

        if ($url === '' && empty($spec['link_data']['link'])) {
            throw new Exception('Creative has no destination URL.');
        }

        $newCreativeId = $this->createInstagramCreativeOnMeta($accountId, $creative, $adSet, $url);
        $inlineSpec = $this->buildInlineLinkCreativeSpec($creative, $adSet, $url);

        if (empty($inlineSpec['object_story_spec']['instagram_user_id'])) {
            throw new Exception('Could not build an Instagram-enabled creative.');
        }

        $creative->update([
            'meta_id' => $newCreativeId,
            'json_payload' => [
                'name' => $creative->name,
                'object_story_spec' => $inlineSpec['object_story_spec'],
            ],
        ]);

        Log::info('IG_REPAIR_CREATIVE_OK', [
            'creative_id' => $creative->id,
            'meta_creative_id' => $newCreativeId,
        ]);

        return true;
    }

    public function repairAd(Ad $ad, bool $recreateCreative = true): bool
    {
        $ad->loadMissing(['creative', 'adSet.campaign.adAccount']);

        if (empty($ad->meta_ad_id)) {
            return false;
        }

        if (! $ad->adSet?->meta_id) {
            throw new Exception('Ad set is not synced with Meta.');
        }

        if (! $ad->creative) {
            throw new Exception('Creative is missing.');
        }

        $stored = is_array($ad->creative->json_payload) ? $ad->creative->json_payload : [];
        $spec = is_array($stored['object_story_spec'] ?? null) ? $stored['object_story_spec'] : [];
        $pageId = (string) ($spec['page_id'] ?? $ad->adSet?->campaign?->meta_page_id ?? TenantScope::pageId() ?? config('services.meta.page_id', ''));
        $accountId = $ad->adSet?->campaign?->adAccount?->meta_id;
        $this->assertInstagramConfigured($pageId, $accountId);

        $this->repairAdSet($ad->adSet);

        if ($recreateCreative) {
            $this->repairCreative($ad->creative, true);
        }

        $ad->creative->refresh();

        $creativeMetaId = (string) $ad->creative->meta_id;
        if ($creativeMetaId === '') {
            throw new Exception('Creative has no Meta ID after repair.');
        }

        $response = $this->meta->attachCreativeToAd((string) $ad->meta_ad_id, $creativeMetaId);

        if (isset($response['error'])) {
            throw new Exception($response['error']['message'] ?? 'Meta failed to attach Instagram creative.');
        }

        Log::info('IG_REPAIR_AD_OK', [
            'ad_id' => $ad->id,
            'meta_ad_id' => $ad->meta_ad_id,
            'creative_meta_id' => $creativeMetaId,
        ]);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildInlineLinkCreativeSpec(Creative $creative, ?AdSet $adset, string $landingUrl): array
    {
        $stored = is_array($creative->json_payload) ? $creative->json_payload : [];
        $spec = is_array($stored['object_story_spec'] ?? null) ? $stored['object_story_spec'] : [];
        $linkData = is_array($spec['link_data'] ?? null) ? $spec['link_data'] : [];

        $pageId = (string) (
            $spec['page_id']
            ?? $adset?->campaign?->meta_page_id
            ?? $creative->campaign?->meta_page_id
            ?? TenantScope::pageId()
            ?? config('services.meta.page_id', '')
        );

        if ($pageId === '' && $adset && ! empty($adset->meta_id)) {
            $metaAdSet = $this->meta->getAdSet((string) $adset->meta_id);
            $po = $metaAdSet['promoted_object'] ?? null;
            if (is_array($po) && ! empty($po['page_id'])) {
                $pageId = (string) $po['page_id'];
            }
        }

        $linkData['link'] = $landingUrl;
        $linkData['message'] = $this->sanitizeLinkMessage(
            (string) ($linkData['message'] ?? $creative->body ?? '')
        );
        $linkData['name'] = $linkData['name'] ?? ($creative->headline ?? $creative->name);

        if (empty($linkData['image_hash']) && ! empty($creative->image_hash)) {
            $linkData['image_hash'] = $creative->image_hash;
        }

        $ctaType = $creative->call_to_action ?: 'LEARN_MORE';
        $linkData['call_to_action'] = [
            'type' => $ctaType,
            'value' => ['link' => $landingUrl],
        ];

        $objectStorySpec = [
            'page_id' => $pageId,
            'link_data' => $linkData,
        ];

        $accountId = $adset?->campaign?->adAccount?->meta_id
            ?? $creative->campaign?->adAccount?->meta_id
            ?? TenantScope::adAccountMetaId();

        $ig = trim((string) ($spec['instagram_user_id'] ?? ''));
        if ($ig === '') {
            $ig = $this->meta->resolveInstagramUserId($pageId, $accountId) ?? '';
        }
        if ($ig !== '') {
            $objectStorySpec['instagram_user_id'] = $ig;
        }

        return [
            'name' => (string) ($creative->name ?? 'Ad Creative'),
            'object_story_spec' => $objectStorySpec,
        ];
    }

    public function createInstagramCreativeOnMeta(
        string $accountId,
        Creative $creative,
        ?AdSet $adset,
        ?string $landingUrl = null
    ): string {
        $url = $landingUrl ?? $this->meta->normalizeLandingUrlForMeta(
            (string) ($creative->destination_url ?? ''),
            false
        );

        $creativePayload = [
            'name' => (string) ($creative->name ?? 'Creative').' IG '.now()->format('YmdHis'),
            'object_story_spec' => $this->buildInlineLinkCreativeSpec($creative, $adset, $url)['object_story_spec'],
        ];

        $created = $this->meta->createCreative($accountId, $creativePayload);

        if (empty($created['id'])) {
            throw new Exception('Meta creative creation failed.');
        }

        return (string) $created['id'];
    }

    protected function sanitizeLinkMessage(string $message): string
    {
        $message = trim($message);
        $message = preg_replace('/\s*Destination\s+URL\s*\r?\n.*$/is', '', $message) ?? $message;

        return trim($message);
    }

    protected function resolveAdSetForCreative(Creative $creative): ?AdSet
    {
        if ($creative->relationLoaded('adset') && $creative->adset) {
            return $creative->adset;
        }

        if ($creative->adset_id) {
            return AdSet::query()->find($creative->adset_id);
        }

        if ($creative->campaign_id) {
            return AdSet::query()
                ->where('campaign_id', $creative->campaign_id)
                ->whereNotNull('meta_id')
                ->first();
        }

        return null;
    }

    protected function resolveAccountIdForCreative(Creative $creative, ?AdSet $adSet): ?string
    {
        $raw = $adSet?->campaign?->adAccount?->meta_id
            ?? $creative->campaign?->adAccount?->meta_id
            ?? TenantScope::adAccountMetaId();

        if (! $raw) {
            return null;
        }

        $accountId = (string) $raw;

        return str_starts_with($accountId, 'act_') ? $accountId : 'act_'.$accountId;
    }

    /**
     * @param  array<string, mixed>  $targeting
     */
    protected function adSetMissingInstagram(array $targeting): bool
    {
        $platforms = $targeting['publisher_platforms'] ?? [];

        return ! is_array($platforms) || ! in_array('instagram', $platforms, true);
    }

    protected function campaignsQuery()
    {
        return TenantScope::campaigns(Campaign::query());
    }

    protected function adSetsQuery()
    {
        return TenantScope::adSets(AdSet::query());
    }

    protected function creativesQuery()
    {
        return TenantScope::creatives(Creative::query());
    }

    protected function adsQuery()
    {
        return TenantScope::ads(Ad::query());
    }
}
