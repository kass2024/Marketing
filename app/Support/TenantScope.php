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

    public static function pageId(): ?string
    {
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
     * All businesses advertise through the platform main Meta ad account.
     */
    public static function platformAdAccountMetaId(): ?string
    {
        return self::formatMetaAccountId(config('services.meta.ad_account_id'));
    }

    public static function adAccountMetaId(): ?string
    {
        return self::platformAdAccountMetaId();
    }

    public static function resolveAdAccount(): ?AdAccount
    {
        $metaId = self::platformAdAccountMetaId();

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

    public static function ensurePlatformAdAccount(?string $displayName = null): ?AdAccount
    {
        $metaId = self::platformAdAccountMetaId();

        if (! $metaId) {
            return null;
        }

        return AdAccount::firstOrCreate(
            ['meta_id' => $metaId],
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

        if ($client->meta_page_id) {
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

    public static function assertCampaign(Campaign $campaign): void
    {
        $client = self::currentClient();

        if (! $client) {
            return;
        }

        if ((int) $campaign->client_id !== (int) $client->id) {
            abort(403, 'This campaign belongs to another business.');
        }

        if ($client->meta_page_id && $campaign->meta_page_id !== $client->meta_page_id) {
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

        return array_values(array_filter($pages, function (array $page) use ($pageId) {
            return (string) ($page['id'] ?? '') === (string) $pageId;
        }));
    }

    public static function campaignAttributes(): array
    {
        $client = self::currentClient();

        if (! $client) {
            return [];
        }

        $account = self::resolveAdAccount();

        return array_filter([
            'client_id' => $client->id,
            'meta_page_id' => $client->meta_page_id,
            'ad_account_id' => $account?->id,
        ]);
    }
}
