<?php

namespace App\Http\Middleware;

use App\Support\TenantScope;
use Closure;
use Illuminate\Http\Request;

class EnsureMetaConnected
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (! $user || ! $user->client) {
            return redirect()
                ->route('client.dashboard')
                ->with('error', 'Client account not found.');
        }

        if (TenantScope::tenantsSharePlatformMeta()) {
            if (! app(\App\Services\Tenant\TenantConnectionResolver::class)->platformDefault()) {
                return redirect()
                    ->route('admin.dashboard')
                    ->with('error', 'Platform Meta account is not configured yet.');
            }

            return $next($request);
        }

        if (! $user->client->hasPublishingProfile()) {
            if ($user->client->needsWhatsAppVerification()) {
                return redirect()
                    ->route('register.whatsapp.verify')
                    ->with('error', 'Verify your business WhatsApp number with Meta before creating ads.');
            }

            return redirect()
                ->route('client.profile.edit')
                ->with('error', 'Set your Facebook Page and business WhatsApp number before creating ads.');
        }

        if (! app(\App\Services\Tenant\TenantConnectionResolver::class)->platformDefault()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'Platform Meta API is not configured. Contact support.');
        }

        return $next($request);
    }
}
