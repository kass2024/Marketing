<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Tenant\TenantMetaPageSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterPageSearchController extends Controller
{
    public function __invoke(Request $request, TenantMetaPageSearchService $search): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'pages' => [],
                'message' => 'Type at least 2 characters to search.',
            ]);
        }

        return response()->json([
            'pages' => $search->search($query),
        ]);
    }
}
