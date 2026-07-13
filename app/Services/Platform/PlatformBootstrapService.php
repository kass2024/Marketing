<?php

namespace App\Services\Platform;

use App\Models\Client;
use App\Models\PlatformMetaConnection;
use App\Models\User;
use App\Support\TenantScope;

class PlatformBootstrapService
{
    public function syncFromEnv(?int $connectedByUserId = null): ?PlatformMetaConnection
    {
        $adminId = $connectedByUserId
            ?? User::query()->where('role', 'super_admin')->value('id');

        if (! $adminId) {
            return null;
        }

        $token = config('platform.meta.system_user_token')
            ?: config('platform.whatsapp.access_token');

        $phoneNumberId = config('platform.whatsapp.phone_number_id');

        if (! $token && ! $phoneNumberId) {
            return null;
        }

        $platformClient = Client::query()->firstOrCreate(
            ['is_platform' => true],
            [
                'user_id'              => $adminId,
                'company_name'         => config('platform.meta.page_name', config('app.name')),
                'business_email'       => config('mail.from.address'),
                'subscription_plan'    => Client::PLAN_ENTERPRISE,
                'subscription_status'  => Client::STATUS_ACTIVE,
                'meta_page_id'         => config('platform.meta.page_id'),
                'meta_page_name'       => config('platform.meta.page_name'),
                'meta_ad_account_id'   => ltrim((string) config('platform.meta.ad_account_id'), 'act_'),
            ]
        );

        $encryptedToken = encrypt($token ?: 'pending');

        $wabaId = config('platform.whatsapp.business_id');
        $bmId = config('platform.meta.business_id') ?: null;

        $connection = PlatformMetaConnection::query()->updateOrCreate(
            ['is_platform_default' => true],
            [
                'client_id'                       => $platformClient->id,
                'connected_by'                    => $adminId,
                // Business Manager ID (prefer META_BUSINESS_ID). Never treat WABA id as BM id.
                'business_id'                     => $bmId,
                'business_name'                   => config('platform.meta.page_name'),
                'ad_account_id'                   => ltrim((string) config('platform.meta.ad_account_id'), 'act_'),
                'ad_account_name'                 => config('platform.meta.page_name'),
                'page_id'                         => config('platform.meta.page_id'),
                'page_name'                       => config('platform.meta.page_name'),
                'instagram_business_account_id'   => config('platform.meta.instagram_user_id'),
                'whatsapp_business_id'            => $wabaId,
                'whatsapp_phone_number_id'        => $phoneNumberId,
                'whatsapp_phone_number'           => config('platform.whatsapp.phone_number'),
                // System-user tokens are not OAuth grants — mark required scopes as present
                // so Ad Studio / publish validators do not false-fail.
                'granted_permissions'             => config('services.meta.required_permissions', []),
                'is_active'                       => true,
                'access_token'                    => $encryptedToken,
            ]
        );

        if ($token) {
            $connection->storeAccessToken($token);
        }

        TenantScope::ensurePlatformAdAccount(config('platform.meta.page_name'), $platformClient->id);

        return $connection->fresh();
    }
}
