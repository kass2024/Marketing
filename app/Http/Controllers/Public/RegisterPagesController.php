<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\MetaAdsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegisterPagesController extends Controller
{
    public function __invoke(MetaAdsService $meta): JsonResponse
    {
        try {
            $pages = $meta->getPages();

            return response()->json([
                'pages' => array_values(array_map(function (array $page) {
                    return [
                        'id' => (string) ($page['id'] ?? ''),
                        'name' => (string) ($page['name'] ?? 'Facebook Page'),
                    ];
                }, $pages)),
            ]);
        } catch (Throwable $e) {
            Log::warning('REGISTER_PAGES_FETCH_FAILED', [
                'error' => $e->getMessage(),
            ]);

            $fallbackId = config('services.meta.page_id');

            if ($fallbackId) {
                return response()->json([
                    'pages' => [[
                        'id' => (string) $fallbackId,
                        'name' => (string) config('services.meta.page_name', 'Facebook Page'),
                    ]],
                ]);
            }

            return response()->json([
                'pages' => [],
                'message' => 'Unable to load Facebook pages. Please contact support.',
            ], 503);
        }
    }
}
