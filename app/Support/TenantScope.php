<?php

namespace App\Support;

use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Creative;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantScope
{
    public static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function currentClient(): ?Client
    {
        $user = self::currentUser();

        if (! $user || $user->isSuperAdmin() || $user->isAgent()) {
            return null;
        }

        return $user->client;
    }

    public static function isScoped(): bool
    {
        return self::currentClient() !== null;
    }

    public static function tenantsSharePlatformMeta(): bool
    {
        return (bool) config('platform.tenants_share_platform_meta', false);
    }

    public static function platformControlsApi(): bool
    {
        return (bool) config('platform.platform_controls_api', true);
    }

    public static function platformPageId(): ?string
    {
        return config('platform.meta.page_id') ?: config('services.meta.page_id');
    }

    public static function platformPageName(): ?string
    {
        return config('platform.meta.page_name') ?: config('services.meta.page_name');
    }

    public static function pageId(): ?string
    {
        if (self::tenantsSharePlatformMeta()) {
            return self::platformPageId();
        }

        return self::currentClient()?->meta_page_id;
    }

    public static function clientId(): ?int
    {
        return self::currentClient()?->id;
    }

    public static function formatMetaAccountId(?string $id): ?string
    {
        if (! $id) {
            return null;
        }

        return str_starts_with($id, 'act_') ? $id : 'act_'.$id;
    }

    /**
     * WABA platform default ad account from .env (not shared with xanderbot).
     */
    public static function platformAdAccountMetaId(): ?string
    {
        return self::formatMetaAccountId(config('platform.meta.ad_account_id'));
    }

    /**
     * Meta ad account for API calls: logged-in client's account if set, else platform .env.
     */
    public static function adAccountMetaId(): ?string
    {
        if (self::platformControlsApi() || self::tenantsSharePlatformMeta()) {
            return self::platformAdAccountMetaId();
        }

        $client = self::currentClient();

        if ($client?->meta_ad_account_id) {
            return self::formatMetaAccountId($client->meta_ad_account_id);
        }

        return self::platformAdAccountMetaId();
    }

    public static function whatsappPhoneNumber(): ?string
    {
        if (self::tenantsSharePlatformMeta()) {
            return config('platform.whatsapp.phone_number');
        }

        return self::currentClient()?->whatsapp_phone_number;
    }

    public static function tenantHasPublishingProfile(): bool
    {
        $client = self::currentClient();

        if (! $client || $client->is_platform) {
            return true;
        }

        return filled($client->meta_page_id) && $client->isWhatsAppVerified();
    }

    public static function resolveAdAccount(): ?AdAccount
    {
        $metaId = self::adAccountMetaId();

        if (! $metaId) {
            return AdAccount::query()->whereNotNull('meta_id')->first();
        }

        return AdAccount::query()->where('meta_id', $metaId)->first();
    }

    public static function requireAdAccount(): AdAccount
    {
        $account = self::resolveAdAccount();

        if (! $account || ! $account->meta_id) {
            abort(403, 'No Meta ad account is connected. Configure META_AD_ACCOUNT_ID in platform settings.');
        }

        return $account;
    }

    public static function ensurePlatformAdAccount(?string $displayName = null, ?int $clientId = null): ?AdAccount
    {
        $metaId = self::platformAdAccountMetaId();

        if (! $metaId) {
            return null;
        }

        $clientId = $clientId ?? Client::query()->where('is_platform', true)->value('id');

        if (! $clientId) {
            return null;
        }

        return AdAccount::firstOrCreate(
            ['client_id' => $clientId, 'meta_id' => $metaId],
            [
                'ad_account_id' => ltrim($metaId, 'act_'),
                'name' => $displayName ?: (string) config('app.name', 'Platform Ad Account'),
                'currency' => 'USD',
                'account_status' => 'ACTIVE',
            ]
        );
    }

    public static function campaigns(Builder $query): Builder
    {
        $client = self::currentClient();

        if (! $client) {
            return $query;
        }

        $query->where('client_id', $client->id);

        if (! self::tenantsSharePlatformMeta() && $client->meta_page_id) {
            $query->where('meta_page_id', $client->meta_page_id);
        }

        return $query;
    }

    public static function adSets(Builder $query): Builder
    {
        $client = self::currentClient();

        if (! $client) {
            return $query;
        }

        return $query->whereHas('campaign', fn (Builder $q) => self::campaigns($q));
    }

    public static function creatives(Builder $query): Builder
    {
        $client = self::currentClient();

        if (! $client) {
            return $query;
        }

        return $query->whereHas('campaign', fn (Builder $q) => self::campaigns($q));
    }

    public static function ads(Builder $query): Builder
    {
        $client = self::currentClient();

        if (! $client) {
            return $query;
        }

        return $query->whereHas('adSet.campaign', fn (Builder $q) => self::campaigns($q));
    }

    public static function conversations(Builder $query): Builder
    {
        $client = self::currentClient();

        if (! $client) {
            return $query;
        }

        return $query->where('client_id', $client->id);
    }

    public static function chatbots(Builder $query): Builder
    {
        $client = self::currentClient();

        if (! $client) {
            return $query;
        }

        return $query->where('client_id', $client->id);
    }

    public static function templates(Builder $query): Builder
    {
        $client = self::currentClient();

        if (! $client) {
            return $query;
        }

        return $query->where('client_id', $client->id);
    }

    public static function assertCampaign(Campaign $campaign): void
    {
        $client = self::currentClient();

        if (! $client) {
            return;
        }

        if ((int) $campaign->client_id !== (int) $client->id) {
            abort(403, 'This campaign belongs to another business.');
        }

        if (! self::tenantsSharePlatformMeta() && $client->meta_page_id && $campaign->meta_page_id !== $client->meta_page_id) {
            abort(403, 'This campaign belongs to another Facebook page.');
        }
    }

    public static function assertAdSet(AdSet $adSet): void
    {
        $adSet->loadMissing('campaign');

        if ($adSet->campaign) {
            self::assertCampaign($adSet->campaign);
        }
    }

    public static function assertCreative(Creative $creative): void
    {
        $creative->loadMissing('campaign');

        if ($creative->campaign) {
            self::assertCampaign($creative->campaign);
        }
    }

    public static function assertAd(Ad $ad): void
    {
        $ad->loadMissing('adSet.campaign');

        if ($ad->adSet?->campaign) {
            self::assertCampaign($ad->adSet->campaign);
        }
    }

    public static function assertModel(Model $model): void
    {
        match (true) {
            $model instanceof Campaign => self::assertCampaign($model),
            $model instanceof AdSet => self::assertAdSet($model),
            $model instanceof Creative => self::assertCreative($model),
            $model instanceof Ad => self::assertAd($model),
            default => null,
        };
    }

    /**
     * @return array<int, array{id:string,name:string}>
     */
    public static function filterPages(array $pages): array
    {
        $pageId = self::pageId();

        if (! $pageId) {
            return $pages;
        }

        $filtered = array_values(array_filter($pages, function (array $page) use ($pageId) {
            return (string) ($page['id'] ?? '') === (string) $pageId;
        }));

        if ($filtered !== []) {
            return $filtered;
        }

        if (self::tenantsSharePlatformMeta()) {
            return [[
                'id' => (string) $pageId,
                'name' => (string) (self::platformPageName() ?: 'Facebook Page'),
            ]];
        }

        return $pages;
    }

    public static function campaignAttributes(): array
    {
        $client = self::currentClient();

        if (! $client) {
            return [];
        }

        $account = self::resolveAdAccount();
        $pageId = self::tenantsSharePlatformMeta()
            ? self::platformPageId()
            : $client->meta_page_id;

        return array_filter([
            'client_id' => $client->id,
            'meta_page_id' => $pageId,
            'ad_account_id' => $account?->id,
        ]);
    }
}
