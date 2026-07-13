<?php

namespace App\Services\Meta;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\Creative;
use App\Models\PlatformMetaConnection;
use App\Services\MetaAdsService;
use App\Support\TenantScope;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MarketingPublishService
{
    public function __construct(
        protected MetaAdsService $meta,
        protected ClickToWhatsAppCreativeBuilder $creativeBuilder,
        protected MarketingPreflightValidator $preflight,
        protected MetaConnectionValidator $connectionValidator
    ) {}

    /**
     * Publish full Click-to-WhatsApp campaign from wizard data.
     *
     * @param  array<string, mixed>  $wizardData
     * @return array{campaign: Campaign, adset: AdSet, creative: Creative, ad: Ad}
     */
    public function publishFromWizard(array $wizardData, bool $activate = false): array
    {
        // Soft sync only — never block publish; skip entirely while Meta is rate-limiting
        if (! \Illuminate\Support\Facades\Cache::get('meta_wa_rate_limited')) {
            try {
                app(MetaAutoSyncService::class)->sync(false);
            } catch (\Throwable) {
                // continue; connection validator still runs below
            }
        }

        $connection = $this->connectionValidator->assertValid();
        $preflight = $this->preflight->validateWizard($wizardData, $connection);

        if (! $preflight['valid']) {
            $first = $preflight['errors'][0] ?? ['message' => 'Validation failed'];
            throw new Exception($first['message'].' — '.$first['fix']);
        }

        if (empty($wizardData['image_path']) && empty($wizardData['image_hash']) && empty($wizardData['stock_image_id']) && empty($wizardData['ai_image_path'])) {
            throw new Exception('Upload a creative image before publishing so Meta can deliver the ad.');
        }

        if (empty(trim((string) ($wizardData['primary_text'] ?? '')))) {
            throw new Exception('Primary ad text is required before publishing.');
        }

        $result = DB::transaction(function () use ($wizardData, $connection, $activate) {
            $account = TenantScope::requireAdAccount();
            $accountId = str_starts_with($account->meta_id, 'act_')
                ? $account->meta_id
                : 'act_'.$account->meta_id;

            $pageId = (string) ($wizardData['page_id'] ?? $connection->page_id);
            $instagramUserId = $wizardData['instagram_user_id']
                ?? $connection->instagram_business_account_id
                ?? $this->meta->resolveInstagramUserId($pageId, $account->meta_id);

            $waDestination = trim((string) (
                $wizardData['whatsapp_chat_url']
                ?? $wizardData['whatsapp_phone_number']
                ?? $connection->whatsapp_phone_number
                ?? ''
            ));
            $whatsappPhone = $this->creativeBuilder->phoneFromLink($waDestination) ?? preg_replace('/\D+/', '', $waDestination);
            $status = $activate ? 'ACTIVE' : 'PAUSED';
            $budgetCents = $this->resolveBudgetCents($wizardData);

            $campaign = Campaign::create([
                'ad_account_id' => $account->id,
                'client_id' => TenantScope::clientId(),
                'meta_page_id' => $pageId,
                'platform_meta_connection_id' => $connection->id,
                'name' => $wizardData['name'],
                'objective' => $wizardData['objective'] ?? 'OUTCOME_ENGAGEMENT',
                'marketing_channel' => 'click_to_whatsapp',
                'daily_budget' => $budgetCents,
                'status' => $activate ? Campaign::STATUS_ACTIVE : Campaign::STATUS_PAUSED,
                'meta_effective_status' => $status,
                'wizard_state' => $wizardData,
                'started_at' => $wizardData['start_date'] ?? now(),
                'ended_at' => $wizardData['end_date'] ?? null,
            ]);

            $metaCampaign = $this->meta->createWhatsAppCampaign($accountId, [
                'name' => $campaign->name,
                'objective' => $campaign->objective,
                'status' => $status,
            ]);

            $campaign->update([
                'meta_id' => $metaCampaign['id'] ?? null,
                'meta_effective_status' => $metaCampaign['effective_status'] ?? $status,
            ]);

            $targeting = $this->buildTargeting($wizardData);
            $adSetDefaults = $this->creativeBuilder->whatsAppAdSetDefaults($pageId);

            $adSetAttrs = [
                'campaign_id' => $campaign->id,
                'name' => $wizardData['adset_name'] ?? ($campaign->name.' — Ad Set'),
                'daily_budget' => $budgetCents,
                'optimization_goal' => $adSetDefaults['optimization_goal'],
                'billing_event' => $adSetDefaults['billing_event'],
                'destination_type' => $adSetDefaults['destination_type'],
                'targeting' => $targeting,
                'status' => $status,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('ad_sets', 'start_time')) {
                $adSetAttrs['start_time'] = $wizardData['start_date'] ?? now();
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('ad_sets', 'end_time')) {
                $adSetAttrs['end_time'] = $wizardData['end_date'] ?? null;
            }

            $adSet = AdSet::create($adSetAttrs);

            $metaAdSet = $this->meta->createWhatsAppAdSet($accountId, array_merge($adSetDefaults, [
                'name' => $adSet->name,
                'campaign_id' => $campaign->meta_id,
                'daily_budget' => $budgetCents,
                'targeting' => $targeting,
                'status' => $status,
                'start_time' => isset($wizardData['start_date'])
                    ? strtotime($wizardData['start_date'])
                    : now()->addMinutes(5)->timestamp,
                'end_time' => ! empty($wizardData['end_date']) ? strtotime($wizardData['end_date']) : null,
            ]));

            $adSet->update(['meta_id' => $metaAdSet['id'] ?? null]);

            $imageHash = $wizardData['image_hash'] ?? null;
            if (! $imageHash && ! empty($wizardData['image_path'])) {
                $fullPath = Storage::disk('public')->path($wizardData['image_path']);
                $upload = $this->meta->uploadImage($accountId, $fullPath);
                $image = current($upload['images'] ?? []);
                $imageHash = $image['hash'] ?? null;
            }

            if (! $imageHash) {
                throw new Exception('Meta did not accept the creative image. Re-upload a JPG/PNG (4:5, 1:1, or 9:16) and publish again.');
            }

            $prefill = (string) ($wizardData['whatsapp_prefill_message'] ?? '');
            $fallbackUrl = $this->creativeBuilder->resolveWhatsAppLink($waDestination, $prefill);

            $creativeInput = [
                'page_id' => $pageId,
                'instagram_user_id' => $instagramUserId,
                'headline' => $wizardData['headline'] ?? $wizardData['name'],
                'primary_text' => $wizardData['primary_text'] ?? $wizardData['body'] ?? '',
                'description' => $wizardData['description'] ?? '',
                'image_hash' => $imageHash,
                'whatsapp_chat_url' => str_starts_with($waDestination, 'http') ? $waDestination : null,
                'whatsapp_phone_number' => $whatsappPhone,
                'whatsapp_prefill_message' => $prefill,
            ];

            $creativePayload = $this->creativeBuilder->buildCreativePayload(
                $wizardData['creative_name'] ?? ($campaign->name.' — Creative'),
                $creativeInput
            );

            $metaCreative = $this->meta->createClickToWhatsAppCreative($accountId, $creativePayload);

            $creative = Creative::create([
                'campaign_id' => $campaign->id,
                'adset_id' => $adSet->id,
                'name' => $creativePayload['name'],
                'headline' => $wizardData['headline'] ?? null,
                'body' => $wizardData['primary_text'] ?? $wizardData['body'] ?? null,
                'description' => $wizardData['description'] ?? null,
                'call_to_action' => $this->creativeBuilder->buildObjectStorySpec($creativeInput)['link_data']['call_to_action']['type'] ?? 'WHATSAPP_MESSAGE',
                'creative_format' => 'click_to_whatsapp',
                'page_id' => $pageId,
                'instagram_user_id' => $instagramUserId,
                'whatsapp_phone_number' => $whatsappPhone,
                'whatsapp_prefill_message' => $prefill,
                'whatsapp_chat_url' => str_starts_with($waDestination, 'http') ? $fallbackUrl : null,
                'whatsapp_fallback_url' => $fallbackUrl,
                'destination_url' => $fallbackUrl,
                'image_url' => $wizardData['image_path'] ?? null,
                'image_hash' => $imageHash,
                'meta_id' => $metaCreative['id'] ?? null,
                'json_payload' => $creativePayload,
                'status' => Creative::STATUS_ACTIVE,
            ]);

            $ad = Ad::create([
                'adset_id' => $adSet->id,
                'creative_id' => $creative->id,
                'name' => $wizardData['ad_name'] ?? ($campaign->name.' — Ad'),
                'status' => $status,
            ]);

            $metaAd = $this->meta->createAd($accountId, [
                'name' => $ad->name,
                'adset_id' => $adSet->meta_id,
                'status' => $status,
                'creative' => ['id' => $metaCreative['id']],
            ]);

            $ad->update([
                'meta_ad_id' => $metaAd['id'] ?? null,
                'meta_effective_status' => $metaAd['effective_status'] ?? $status,
            ]);

            // Re-read Meta campaign so local delivery status matches Ads Manager
            if ($campaign->meta_id) {
                try {
                    $fresh = $this->meta->getCampaign($campaign->meta_id);
                    $campaign->update([
                        'status' => Campaign::normalizeStatus($fresh['effective_status'] ?? $fresh['status'] ?? $status),
                        'meta_effective_status' => $fresh['effective_status'] ?? $fresh['status'] ?? $status,
                    ]);
                } catch (\Throwable) {
                    // keep local status from publish
                }
            }

            Log::info('MARKETING_PUBLISH_SUCCESS', [
                'campaign_id' => $campaign->id,
                'meta_campaign_id' => $campaign->meta_id,
                'meta_ad_id' => $ad->meta_ad_id ?? $ad->meta_id ?? null,
                'activate' => $activate,
                'status' => $status,
            ]);

            return [
                'campaign' => $campaign->fresh(),
                'adset' => $adSet->fresh(),
                'creative' => $creative,
                'ad' => $ad->fresh(),
            ];
        });

        return $result;
    }

    /**
     * @param  array<string, mixed>  $wizardData
     * @return array<string, mixed>
     */
    protected function buildTargeting(array $wizardData): array
    {
        if (! empty($wizardData['targeting']) && is_array($wizardData['targeting'])) {
            return $wizardData['targeting'];
        }

        $countries = $wizardData['countries'] ?? [];
        if (! is_array($countries)) {
            $countries = array_values(array_filter([(string) $countries]));
        }
        if ($countries === []) {
            throw new \InvalidArgumentException('Select at least one country in Locations before publishing.');
        }
        $geo = $this->meta->buildGeoLocations(
            $countries,
            $wizardData['cities'] ?? [],
            $wizardData['regions'] ?? []
        );

        $targeting = array_merge(
            ClickToWhatsAppCreativeBuilder::defaultPlacements(),
            $wizardData['placements'] ?? [],
            ['geo_locations' => $geo]
        );

        if (! empty($wizardData['age_min'])) {
            $targeting['age_min'] = (int) $wizardData['age_min'];
        }
        if (! empty($wizardData['age_max'])) {
            $targeting['age_max'] = (int) $wizardData['age_max'];
        }
        if (! empty($wizardData['genders'])) {
            $targeting['genders'] = array_map('intval', (array) $wizardData['genders']);
        }
        if (! empty($wizardData['interests'])) {
            $targeting['flexible_spec'] = [[
                'interests' => collect($wizardData['interests'])->map(fn ($id) => ['id' => (string) $id])->values()->all(),
            ]];
        }

        return $targeting;
    }

    /**
     * Meta daily_budget is account minor units (cents for USD). $5 → 500.
     *
     * @param  array<string, mixed>  $wizardData
     */
    protected function resolveBudgetCents(array $wizardData): int
    {
        if (isset($wizardData['daily_budget_dollars']) && $wizardData['daily_budget_dollars'] !== '' && $wizardData['daily_budget_dollars'] !== null) {
            return (int) round(max(0, (float) $wizardData['daily_budget_dollars']) * 100);
        }

        $raw = (float) ($wizardData['daily_budget'] ?? 0);
        if ($raw <= 0) {
            return 0;
        }

        // Bare "5" from the form means $5, not 5 cents.
        if ($raw < 100) {
            return (int) round($raw * 100);
        }

        return (int) round($raw);
    }
}
