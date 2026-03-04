<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\MetaAdsService;

class AnalyticsController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::all();
        return view('admin.analytics.index', compact('campaigns'));
    }
}