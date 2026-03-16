<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMetaConnected
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user || !$user->client) {
            return redirect()
                ->route('client.dashboard')
                ->with('error','Client account not found.');
        }

        $client = $user->client;

        if (!$client->metaConnection) {
            return redirect()
                ->route('client.meta.index')
                ->with('error','Please connect your Meta account first.');
        }

        return $next($request);
    }
}