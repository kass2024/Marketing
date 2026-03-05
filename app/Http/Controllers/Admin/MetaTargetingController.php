<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaTargetingController extends Controller
{
    /**
     * Search Meta Ads Interests
     */
    public function searchInterests(Request $request)
    {
        $query = trim($request->get('q', ''));

        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        try {

            $token = config('services.meta.token');
            $graphUrl = config('services.meta.graph_url');
            $version = config('services.meta.graph_version');
            $accountId = config('services.meta.ad_account_id');

            if (!$token) {
                Log::error('Meta token missing');
                return response()->json([
                    'success' => false,
                    'data' => []
                ]);
            }

            $url = "{$graphUrl}/{$version}/search";

            $response = Http::timeout(10)->get($url, [
                'type' => 'adinterest',
                'q' => $query,
                'limit' => 20,
                'access_token' => $token
            ]);

            if (!$response->successful()) {

                Log::error('Meta interest search failed', [
                    'query' => $query,
                    'response' => $response->body()
                ]);

                return response()->json([
                    'success' => false,
                    'data' => []
                ]);
            }

            $result = $response->json();

            if (!isset($result['data'])) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            // Format results for TomSelect
            $formatted = collect($result['data'])->map(function ($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name']
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $formatted
            ]);

        } catch (\Exception $e) {

            Log::error('Meta interest search error', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'data' => []
            ]);
        }
    }
}