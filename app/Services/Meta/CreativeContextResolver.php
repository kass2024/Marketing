<?php

namespace App\Services\Meta;

use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\Creative;
use App\Models\PlatformMetaConnection;
use App\Services\Tenant\TenantConnectionResolver;
use App\Support\TenantScope;

class CreativeContextResolver
{
    /**
     * Build dynamic form context from an existing campaign + ad set.
     *
     * @return array<string, mixed>
     */
    public function resolve(?Campaign $campaign, ?AdSet $adset, ?PlatformMetaConnection $connection = null): array
    {
        $connection = $connection ?? app(TenantConnectionResolver::class)->forCurrentUser();

        if (! $campaign && $adset) {
            $campaign = $adset->campaign;
        }

        $targeting = is_array($adset?->targeting) ? $adset->targeting : [];
        $placements = $this->placementsFromTargeting($targeting);

        return [
            'campaign_id' => $campaign?->id,
            'campaign_name' => $campaign?->name,
            'campaign_objective' => $campaign?->objective,
            'campaign_goal' => $this->goalFromCampaign($campaign),
            'campaign_status' => $campaign?->status,
            'campaign_meta_id' => $campaign?->meta_id,
            'campaign_meta_synced' => ! empty($campaign?->meta_id),

            'adset_id' => $adset?->id,
            'adset_name' => $adset?->name,
            'adset_status' => $adset?->status,
            'adset_meta_id' => $adset?->meta_id,
            'adset_meta_synced' => ! empty($adset?->meta_id),
            'adset_daily_budget' => $adset?->daily_budget,
            'adset_optimization_goal' => $adset?->optimization_goal,
            'adset_destination_type' => $adset?->destination_type,
            'adset_targeting' => $targeting,

            'service_name' => $campaign?->name ?? '',
            'target_audience' => $this->audienceFromTargeting($targeting),
            'placements' => $placements,
            'page_id' => $campaign?->meta_page_id ?? $connection?->page_id ?? TenantScope::platformPageId(),
            'whatsapp_phone_number' => $connection?->whatsapp_phone_number ?? '',

            'existing_creatives_count' => $adset
                ? Creative::query()->where('adset_id', $adset->id)->count()
                : 0,
            'existing_ads_count' => $adset?->ads()->count() ?? 0,

            'marketing_channel' => $campaign?->marketing_channel,
            'is_whatsapp_campaign' => in_array($adset?->destination_type, ['WHATSAPP', null], true)
                || ($campaign?->marketing_channel ?? '') === 'click_to_whatsapp',
        ];
    }

    public function goalFromCampaign(?Campaign $campaign): string
    {
        if (! $campaign) {
            return '';
        }

        $wizard = is_array($campaign->wizard_state) ? $campaign->wizard_state : [];
        if (! empty($wizard['campaign_goal'])) {
            return (string) $wizard['campaign_goal'];
        }

        return match (strtoupper((string) $campaign->objective)) {
            'OUTCOME_ENGAGEMENT', 'MESSAGES' => 'Start WhatsApp conversations and engagement',
            'OUTCOME_LEADS' => 'Generate qualified leads via WhatsApp',
            'OUTCOME_SALES' => 'Drive sales through WhatsApp chat',
            'OUTCOME_TRAFFIC' => 'Send interested users to WhatsApp',
            'OUTCOME_AWARENESS' => 'Build brand awareness and chat interest',
            default => (string) $campaign->objective,
        };
    }

    /**
     * @param  array<string, mixed>  $targeting
     */
    public function audienceFromTargeting(array $targeting): string
    {
        $parts = [];

        $countries = $targeting['geo_locations']['countries'] ?? [];
        if ($countries !== []) {
            $parts[] = 'Countries: '.implode(', ', $countries);
        }

        $cities = $targeting['geo_locations']['cities'] ?? [];
        if ($cities !== []) {
            $cityNames = collect($cities)->pluck('name')->filter()->take(5)->implode(', ');
            if ($cityNames !== '') {
                $parts[] = 'Cities: '.$cityNames;
            }
        }

        if (! empty($targeting['age_min']) || ! empty($targeting['age_max'])) {
            $parts[] = 'Ages '.($targeting['age_min'] ?? 18).'-'.($targeting['age_max'] ?? 65);
        }

        if (! empty($targeting['genders'])) {
            $genders = collect($targeting['genders'])->map(fn ($g) => match ((int) $g) {
                1 => 'male', 2 => 'female', default => 'all',
            })->unique()->implode(', ');
            $parts[] = 'Gender: '.$genders;
        }

        if (! empty($targeting['flexible_spec'])) {
            $parts[] = 'Interest targeting applied';
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Broad audience';
    }

    /**
     * Map ad set targeting to creative builder placement keys.
     *
     * @param  array<string, mixed>  $targeting
     * @return list<string>
     */
    public function placementsFromTargeting(array $targeting): array
    {
        if ($targeting === []) {
            return array_keys(CreativeTemplateRegistry::placements());
        }

        $selected = [];
        $registry = CreativeTemplateRegistry::placements();
        $platforms = $targeting['publisher_platforms'] ?? ['facebook', 'instagram'];

        $fbPositions = $targeting['facebook_positions'] ?? ['feed', 'story', 'facebook_reels'];
        $igPositions = $targeting['instagram_positions'] ?? ['stream', 'story', 'reels'];

        if (in_array('facebook', $platforms, true)) {
            foreach ($fbPositions as $pos) {
                $key = match ($pos) {
                    'feed' => 'facebook_feed',
                    'story' => 'facebook_story',
                    'facebook_reels', 'reels' => 'facebook_reels',
                    default => null,
                };
                if ($key && isset($registry[$key])) {
                    $selected[] = $key;
                }
            }
        }

        if (in_array('instagram', $platforms, true)) {
            foreach ($igPositions as $pos) {
                $key = match ($pos) {
                    'stream', 'feed' => 'instagram_feed',
                    'story' => 'instagram_story',
                    'reels' => 'instagram_reels',
                    default => null,
                };
                if ($key && isset($registry[$key])) {
                    $selected[] = $key;
                }
            }
        }

        return $selected !== [] ? array_values(array_unique($selected)) : array_keys($registry);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adsetsPayload(Campaign $campaign): array
    {
        return $campaign->adsets()
            ->withCount(['ads', 'creatives'])
            ->latest()
            ->get()
            ->map(fn (AdSet $adset) => [
                'id' => $adset->id,
                'name' => $adset->name,
                'status' => $adset->status,
                'meta_id' => $adset->meta_id,
                'meta_synced' => ! empty($adset->meta_id),
                'daily_budget' => $adset->daily_budget,
                'optimization_goal' => $adset->optimization_goal,
                'destination_type' => $adset->destination_type,
                'ads_count' => $adset->ads_count,
                'creatives_count' => $adset->creatives_count,
                'context' => $this->resolve($campaign, $adset),
            ])
            ->values()
            ->all();
    }
}
