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

            $messagingApps = $this->creativeBuilder->resolveMessagingApps($wizardData);
            Log::info('MESSAGING_DESTINATIONS', $messagingApps);

            // Dropdown phone ALWAYS wins over a leftover wa.me / platform default URL.
            // That stale link (e.g. platform …0350) was overriding the selected WABA number (…5329).
            $phoneField = trim((string) ($wizardData['whatsapp_phone_number'] ?? ''));
            if ($phoneField === '__custom__') {
                $phoneField = '';
            }
            $chatUrl = trim((string) ($wizardData['whatsapp_chat_url'] ?? ''));
            $connectionPhone = trim((string) ($connection->whatsapp_phone_number ?? ''));

            if ($phoneField !== '') {
                $waDestination = $phoneField;
            } elseif ($chatUrl !== '') {
                $waDestination = $chatUrl;
            } elseif (! empty($messagingApps['whatsapp'])) {
                // Never prefer .env / platform default when WhatsApp accounts are selected in UI —
                // only use connection as last resort if nothing else was posted.
                $waDestination = $connectionPhone;
            } else {
                $waDestination = '';
            }

            $whatsappPhone = $waDestination !== ''
                ? ((string) ($this->creativeBuilder->phoneFromLink($waDestination)
                    ?? (preg_replace('/\D+/', '', $waDestination) ?: null)
                    ?? ''))
                : '';
            $whatsappPhoneId = $whatsappPhone !== ''
                ? $this->resolveWhatsAppBusinessPhoneNumberId(
                    (string) $whatsappPhone,
                    $wizardData,
                    $connection
                )
                : null;
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
            $adSetDefaults = $this->creativeBuilder->messagingAdSetDefaults(
                $pageId,
                $messagingApps,
                (string) $whatsappPhone,
                $whatsappPhoneId
            );

            if (! empty($messagingApps['whatsapp']) && empty($adSetDefaults['promoted_object']['whatsapp_phone_number'])) {
                throw new Exception(
                    'Select a WhatsApp Business number from your WhatsApp accounts before publishing (do not rely on the platform default).'
                );
            }

            if (! empty($messagingApps['instagram']) && empty($instagramUserId)) {
                throw new Exception(
                    'Instagram is selected as a message destination — choose an Instagram account in Ad set identities.'
                );
            }

            Log::info('WA_PROMOTED_OBJECT', [
                'destination_type' => $adSetDefaults['destination_type'] ?? null,
                'promoted_object' => $adSetDefaults['promoted_object'] ?? [],
                'messaging_apps' => $messagingApps,
            ]);

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
            $startTs = $this->resolveScheduleTimestamp(
                $wizardData['start_time_unix'] ?? null,
                $wizardData['start_date'] ?? null,
                true
            );
            $endTs = $this->resolveScheduleTimestamp(
                $wizardData['end_time_unix'] ?? null,
                ! empty($wizardData['end_date']) ? $wizardData['end_date'] : null,
                false
            );
            if (\Illuminate\Support\Facades\Schema::hasColumn('ad_sets', 'start_time')) {
                $adSetAttrs['start_time'] = \Carbon\Carbon::createFromTimestamp($startTs);
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('ad_sets', 'end_time')) {
                $adSetAttrs['end_time'] = $endTs
                    ? \Carbon\Carbon::createFromTimestamp($endTs)
                    : null;
            }

            $adSet = AdSet::create($adSetAttrs);

            $metaAdSet = $this->meta->createWhatsAppAdSet($accountId, array_merge($adSetDefaults, [
                'name' => $adSet->name,
                'campaign_id' => $campaign->meta_id,
                'daily_budget' => $budgetCents,
                'targeting' => $targeting,
                'status' => $status,
                'whatsapp_phone_number' => (string) $whatsappPhone,
                'whatsapp_phone_number_id' => $whatsappPhoneId,
                'whats_app_business_phone_number_id' => $whatsappPhoneId,
                'start_time' => $startTs,
                'end_time' => $endTs,
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
            $fallbackUrl = $whatsappPhone !== ''
                ? $this->creativeBuilder->buildWhatsAppLink((string) $whatsappPhone, $prefill)
                : '';

            $creativeInput = [
                'page_id' => $pageId,
                'instagram_user_id' => $instagramUserId,
                'headline' => $wizardData['headline'] ?? $wizardData['name'],
                'primary_text' => $wizardData['primary_text'] ?? $wizardData['body'] ?? '',
                'description' => $wizardData['description'] ?? '',
                'image_hash' => $imageHash,
                'whatsapp_phone_number' => $whatsappPhone,
                'whatsapp_prefill_message' => $prefill,
            ];

            $creativePayload = $this->creativeBuilder->buildMessagingCreativePayload(
                $wizardData['creative_name'] ?? ($campaign->name.' — Creative'),
                $creativeInput,
                $messagingApps
            );

            $metaCreative = $this->meta->createClickToWhatsAppCreative($accountId, $creativePayload);

            $creative = Creative::create([
                'campaign_id' => $campaign->id,
                'adset_id' => $adSet->id,
                'name' => $creativePayload['name'],
                'headline' => $wizardData['headline'] ?? null,
                'body' => $wizardData['primary_text'] ?? $wizardData['body'] ?? null,
                'description' => $wizardData['description'] ?? null,
                'call_to_action' => $creativePayload['object_story_spec']['link_data']['call_to_action']['type'] ?? 'WHATSAPP_MESSAGE',
                'creative_format' => 'click_to_whatsapp',
                'page_id' => $pageId,
                'instagram_user_id' => $instagramUserId,
                'whatsapp_phone_number' => $whatsappPhone ?: null,
                'whatsapp_prefill_message' => $prefill,
                'whatsapp_chat_url' => $fallbackUrl !== '' ? $fallbackUrl : null,
                'whatsapp_fallback_url' => $fallbackUrl !== '' ? $fallbackUrl : null,
                'destination_url' => $fallbackUrl !== '' ? $fallbackUrl : null,
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
     * Resolve Meta WhatsApp Business phone number ID for promoted_object.
     * Prefer the selected dropdown id / directory match by digits — never a mismatched platform .env number.
     */
    protected function resolveWhatsAppBusinessPhoneNumberId(
        string $digits,
        array $wizardData,
        PlatformMetaConnection $connection
    ): ?string {
        $pick = static function (string $id): ?string {
            $id = trim($id);
            if ($id === '' || str_starts_with($id, 'display:') || $id === 'platform' || ! ctype_digit($id)) {
                return null;
            }

            return $id;
        };

        foreach ([
            (string) ($wizardData['whatsapp_phone_number_id'] ?? ''),
            (string) ($wizardData['phone_number_id'] ?? ''),
        ] as $raw) {
            if ($found = $pick($raw)) {
                return $found;
            }
        }

        $tail = strlen($digits) >= 10 ? substr($digits, -10) : $digits;
        try {
            $directory = app(WhatsAppBusinessAccountService::class)->loadPhoneDirectory($connection);
            foreach ($directory as $phone) {
                if (! is_array($phone)) {
                    continue;
                }
                $display = preg_replace('/\D+/', '', (string) ($phone['display_phone_number'] ?? '')) ?: '';
                $phoneTail = strlen($display) >= 10 ? substr($display, -10) : $display;
                if ($tail !== '' && $phoneTail === $tail) {
                    if ($found = $pick((string) ($phone['id'] ?? ''))) {
                        return $found;
                    }
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        $connectionDigits = preg_replace('/\D+/', '', (string) ($connection->whatsapp_phone_number ?? '')) ?: '';
        $connectionTail = strlen($connectionDigits) >= 10 ? substr($connectionDigits, -10) : $connectionDigits;
        if ($tail !== '' && $connectionTail === $tail) {
            if ($found = $pick((string) ($connection->whatsapp_phone_number_id ?? ''))) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Resolve Meta schedule unix time.
     * Prefer browser-local unix (start_time_unix) — datetime-local strings are naive and
     * were interpreted as UTC, which pushed start into the future → Ads Manager "Scheduled".
     *
     * @param  mixed  $unix
     * @param  mixed  $naiveDatetime
     */
    protected function resolveScheduleTimestamp($unix, $naiveDatetime, bool $defaultToNow): ?int
    {
        $now = now()->timestamp;

        if ($unix !== null && $unix !== '' && is_numeric($unix)) {
            $ts = (int) $unix;
        } elseif ($naiveDatetime !== null && trim((string) $naiveDatetime) !== '') {
            $parsed = strtotime((string) $naiveDatetime);
            $ts = $parsed !== false ? $parsed : null;
        } else {
            $ts = $defaultToNow ? $now : null;
        }

        if ($ts === null) {
            return null;
        }

        // If chosen time is already past (or within ~2 minutes), deliver immediately.
        if ($defaultToNow && $ts <= $now + 120) {
            return $now;
        }

        return $ts;
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
