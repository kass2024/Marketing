<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\MetaWebhookEvent;
use App\Models\PlatformMetaConnection;
use App\Services\Meta\MetaAutoSyncService;
use App\Services\Platform\PlatformBootstrapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TenantMonitorController extends Controller
{
    public function index(MetaAutoSyncService $autoSync): View
    {
        $autoSync->sync(false);

        $tenants = Client::query()
            ->with(['user', 'platformMetaConnections'])
            ->withCount(['campaigns', 'conversations', 'chatbots'])
            ->orderByDesc('is_platform')
            ->orderBy('company_name')
            ->get()
            ->map(function (Client $client) {
                $connection = app(\App\Services\Tenant\TenantConnectionResolver::class)
                    ->forClient($client->is_platform ? null : $client->id);

                $recentWebhooks = 0;
                if ($connection?->whatsapp_phone_number_id) {
                    $recentWebhooks = MetaWebhookEvent::query()
                        ->where('phone_number_id', $connection->whatsapp_phone_number_id)
                        ->where('created_at', '>=', now()->subDay())
                        ->count();
                }

                return [
                    'client'           => $client,
                    'connection'       => $connection,
                    'recent_webhooks'  => $recentWebhooks,
                    'unread_messages'  => Conversation::query()
                        ->where('client_id', $client->id)
                        ->whereHas('messages', fn ($q) => $q->where('direction', 'incoming')->where('is_read', 0))
                        ->count(),
                ];
            });

        $platformConnection = PlatformMetaConnection::query()->platformDefault()->first();

        return view('admin.tenants.index', compact('tenants', 'platformConnection'));
    }

    public function syncPlatform(PlatformBootstrapService $bootstrap, MetaAutoSyncService $autoSync): RedirectResponse
    {
        $connection = $bootstrap->syncFromEnv();
        $autoSync->sync(true);

        if (! $connection) {
            return back()->with('error', 'Could not sync platform account — check META_* and WHATSAPP_* in .env.');
        }

        return back()->with('success', 'Main platform account auto-synced from .env + Meta Graph.');
    }
}
