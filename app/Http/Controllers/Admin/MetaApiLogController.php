<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetaApiLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MetaApiLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = MetaApiLog::query()
            ->when($request->boolean('errors_only'), fn ($q) => $q->where('success', false))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.marketing.logs', [
            'logs' => $logs,
            'errorCount' => MetaApiLog::query()->where('success', false)->where('created_at', '>=', now()->subDay())->count(),
        ]);
    }
}
