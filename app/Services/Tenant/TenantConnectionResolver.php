<?php

namespace App\Services\Tenant;

use App\Models\Client;
use App\Models\PlatformMetaConnection;
use App\Support\TenantScope;

class TenantConnectionResolver
{
    public function forCurrentUser(): ?PlatformMetaConnection
    {
        return $this->forClient(TenantScope::currentClient()?->id);
    }

    public function platformDefault(): ?PlatformMetaConnection
    {
        return PlatformMetaConnection::query()
            ->platformDefault()
            ->active()
            ->first();
    }

    public function forClient(?int $clientId): ?PlatformMetaConnection
    {
        $base = $this->platformDefault();

        if (! $base) {
            return null;
        }

        if (! $clientId) {
            return $base;
        }

        $client = Client::query()->find($clientId);

        return $base->withTenantProfile($client);
    }

    public function resolveByPhoneNumberId(string|int|null $phoneNumberId): ?PlatformMetaConnection
    {
        if ($phoneNumberId === null || $phoneNumberId === '') {
            return null;
        }

        $id = (string) $phoneNumberId;

        $tenantClient = Client::query()
            ->where('whatsapp_phone_number_id', $id)
            ->where('is_platform', false)
            ->first();

        if ($tenantClient) {
            return $this->forClient($tenantClient->id);
        }

        $connection = PlatformMetaConnection::query()
            ->where('whatsapp_phone_number_id', $id)
            ->first();

        if ($connection) {
            return $connection->is_platform_default
                ? $connection
                : $this->forClient($connection->client_id);
        }

        return null;
    }

    public function resolveClientId(PlatformMetaConnection $connection): ?int
    {
        $phoneId = $connection->whatsapp_phone_number_id;

        if ($phoneId) {
            $tenantId = Client::query()
                ->where('whatsapp_phone_number_id', (string) $phoneId)
                ->where('is_platform', false)
                ->value('id');

            if ($tenantId) {
                return (int) $tenantId;
            }
        }

        if ($connection->client_id && ! $connection->is_platform_default) {
            return (int) $connection->client_id;
        }

        if ($connection->is_platform_default) {
            return Client::query()->where('is_platform', true)->value('id');
        }

        if ($connection->connected_by) {
            return Client::query()
                ->where('user_id', $connection->connected_by)
                ->value('id');
        }

        return null;
    }

    public function whatsappPhoneNumber(): ?string
    {
        return TenantScope::whatsappPhoneNumber()
            ?: $this->forCurrentUser()?->whatsapp_phone_number
            ?: config('platform.whatsapp.phone_number');
    }
}
