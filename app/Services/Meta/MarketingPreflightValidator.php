<?php

namespace App\Services\Meta;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\Creative;
use App\Models\PlatformMetaConnection;
use App\Support\TenantScope;
use Exception;

class MarketingPreflightValidator
{
    public function __construct(
        protected MetaConnectionValidator $connectionValidator,
        protected ClickToWhatsAppCreativeBuilder $creativeBuilder
    ) {}

    /**
     * @param  array<string, mixed>  $wizardData
     * @return array{valid: bool, errors: array<int, array{code: string, message: string, fix: string}>, warnings: array<int, string>}
     */
    public function validateWizard(array $wizardData, ?PlatformMetaConnection $connection = null): array
    {
        $errors = [];
        $warnings = [];

        $connectionResult = $this->connectionValidator->validate($connection);
        $errors = array_merge($errors, $connectionResult['errors']);
        $connection = $connectionResult['connection'];

        if (empty($wizardData['name'])) {
            $errors[] = $this->issue('missing_name', 'Campaign name is required.', 'Enter a campaign name in step 1.');
        }

        if (empty($wizardData['objective'])) {
            $errors[] = $this->issue('missing_objective', 'Campaign objective is required.', 'Select an objective in step 3.');
        }

        if (empty($wizardData['page_id']) && empty($connection?->page_id)) {
            $errors[] = $this->issue('missing_page', 'Facebook Page is required.', 'Select a Page in step 2.');
        }

        $phone = trim((string) ($wizardData['whatsapp_chat_url'] ?? $wizardData['whatsapp_phone_number'] ?? $connection?->whatsapp_phone_number ?? ''));
        if ($phone === '') {
            $errors[] = $this->issue('missing_whatsapp_number', 'WhatsApp chat link or phone is required.', 'Enter a wa.me link or phone number in step 8.');
        }

        $budget = (int) ($wizardData['daily_budget'] ?? 0);
        if (isset($wizardData['daily_budget_dollars']) && $wizardData['daily_budget_dollars'] !== '' && $wizardData['daily_budget_dollars'] !== null) {
            $budget = (int) round(max(0, (float) $wizardData['daily_budget_dollars']) * 100);
        } elseif ($budget > 0 && $budget < 100) {
            // Treat bare "5" as $5 (Meta minor units), not 5 cents.
            $budget = $budget * 100;
        }
        // $5/day (500 cents) is valid in Meta Ads Manager; allow from $1 (100 cents).
        if ($budget < 100) {
            $errors[] = $this->issue('budget_too_low', 'Daily budget must be at least $1.00 (Meta minimum for many USD accounts).', 'Set daily budget to $5 or more in the Ad set step — $5 works in Meta Ads Manager.');
        }

        $countries = $wizardData['countries'] ?? [];
        if (is_string($countries)) {
            $countries = array_filter(array_map('trim', explode(',', $countries)));
        }
        $hasGeo = $countries !== []
            || ! empty($wizardData['cities'])
            || ! empty($wizardData['regions'])
            || ! empty($wizardData['targeting']['geo_locations']);
        if (! $hasGeo) {
            $errors[] = $this->issue('missing_audience', 'At least one target country, region, or city is required.', 'Select locations in the Ad set step.');
        }

        if (empty($wizardData['primary_text']) && empty($wizardData['body'])) {
            $errors[] = $this->issue('missing_ad_text', 'Primary ad text is required.', 'Write ad copy in step 7.');
        }

        if (
            empty($wizardData['image_path'])
            && empty($wizardData['image_hash'])
            && empty($wizardData['existing_creative_id'])
            && empty($wizardData['stock_image_id'])
            && empty($wizardData['ai_image_path'])
        ) {
            $errors[] = $this->issue('missing_media', 'Ad image or video is required.', 'Upload media, pick a standard template, or generate with AI.');
        }

        $placements = $wizardData['placements'] ?? ClickToWhatsAppCreativeBuilder::defaultPlacements();
        $platforms = $placements['publisher_platforms'] ?? [];
        if (! array_intersect($platforms, ['facebook', 'instagram'])) {
            $errors[] = $this->issue('placement_unsupported', 'Click-to-WhatsApp requires Facebook and/or Instagram placements.', 'Enable Facebook feed and/or Instagram feed in step 4.');
        }

        if (in_array('audience_network', $platforms, true)) {
            $warnings[] = 'Audience Network may not support all Click-to-WhatsApp formats.';
        }

        if (empty($wizardData['instagram_user_id']) && empty($connection?->instagram_business_account_id)) {
            $warnings[] = 'No Instagram account linked — ads will run on Facebook only unless you connect Instagram.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{valid: bool, errors: array<int, array{code: string, message: string, fix: string}>, warnings: array<int, string>}
     */
    public function validatePublish(Campaign $campaign): array
    {
        $errors = [];
        $warnings = [];

        $connectionResult = $this->connectionValidator->validate();
        $errors = array_merge($errors, $connectionResult['errors']);

        if (! $campaign->meta_id) {
            $errors[] = $this->issue('campaign_not_synced', 'Campaign is not published to Meta.', 'Publish the campaign from the wizard or sync to Meta first.');
        }

        $adSets = $campaign->adsets()->get();
        if ($adSets->isEmpty()) {
            $errors[] = $this->issue('missing_adset', 'At least one ad set is required.', 'Complete wizard steps 4–5.');
        }

        foreach ($adSets as $adSet) {
            if (! $adSet->meta_id) {
                $errors[] = $this->issue('adset_not_synced', "Ad set \"{$adSet->name}\" is not on Meta.", 'Re-publish from the wizard.');
            }
            if ((int) $adSet->daily_budget < 100) {
                // Stored in cents; values like 5 mean $0.05 and are invalid. $5 = 500.
                $errors[] = $this->issue('budget_too_low', "Ad set \"{$adSet->name}\" budget is too low.", 'Set daily budget to at least $5 (as in Meta Ads Manager).');
            }
        }

        $ads = Ad::query()->whereIn('adset_id', $adSets->pluck('id'))->get();
        if ($ads->isEmpty()) {
            $errors[] = $this->issue('missing_ad', 'At least one ad is required.', 'Complete wizard steps 6–8.');
        }

        foreach ($ads as $ad) {
            $creative = $ad->creative;
            if (! $creative) {
                $errors[] = $this->issue('missing_creative', "Ad \"{$ad->name}\" has no creative.", 'Attach a Click-to-WhatsApp creative.');
                continue;
            }

            if ($creative->creative_format === 'click_to_whatsapp'
                && empty($creative->whatsapp_phone_number)
                && empty($creative->whatsapp_chat_url)
                && empty($creative->whatsapp_fallback_url)) {
                $errors[] = $this->issue('whatsapp_number_not_connected', 'Creative is missing WhatsApp chat destination.', 'Set a wa.me link or phone in the creative builder.');
            }

            if (! $creative->meta_id) {
                $errors[] = $this->issue('creative_not_synced', "Creative \"{$creative->name}\" is not on Meta.", 'Re-upload and sync creative.');
            }

            $cta = strtoupper((string) $creative->call_to_action);
            if ($cta && ! in_array($cta, ['WHATSAPP_MESSAGE', 'SEND_MESSAGE'], true)) {
                $warnings[] = "Creative \"{$creative->name}\" CTA is {$cta}; Click-to-WhatsApp expects WHATSAPP_MESSAGE.";
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    public function checklist(array $wizardData, ?PlatformMetaConnection $connection = null): array
    {
        $result = $this->validateWizard($wizardData, $connection);

        return [
            ['label' => 'Token valid', 'ok' => ! collect($result['errors'])->contains('code', 'invalid_token') && ! collect($result['errors'])->contains('code', 'token_expired')],
            ['label' => 'Ad account connected', 'ok' => ! collect($result['errors'])->contains('code', 'missing_ad_account')],
            ['label' => 'Page connected', 'ok' => ! collect($result['errors'])->contains('code', 'missing_page')],
            ['label' => 'Instagram account connected', 'ok' => ! collect($result['warnings'])->contains(fn ($w) => str_contains($w, 'Instagram'))],
            ['label' => 'WhatsApp number connected', 'ok' => ! collect($result['errors'])->contains('code', 'missing_whatsapp_number')],
            ['label' => 'Creative media uploaded', 'ok' => ! collect($result['errors'])->contains('code', 'missing_media')],
            ['label' => 'Budget valid', 'ok' => ! collect($result['errors'])->contains('code', 'budget_too_low')],
            ['label' => 'Audience valid', 'ok' => ! collect($result['errors'])->contains('code', 'missing_audience')],
            ['label' => 'CTA valid (WhatsApp)', 'ok' => ! collect($result['errors'])->contains('code', 'missing_whatsapp_number')],
            ['label' => 'Required fields complete', 'ok' => $result['valid']],
        ];
    }

    /**
     * @return array{code: string, message: string, fix: string}
     */
    protected function issue(string $code, string $message, string $fix): array
    {
        return compact('code', 'message', 'fix');
    }
}
