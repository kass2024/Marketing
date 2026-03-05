<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\AdSet;
use App\Models\Ad;

class AdsManagerController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::latest()->get();

        return view('admin.ads.manager', compact('campaigns'));
    }

    public function adsets($campaignId)
    {
        $adsets = AdSet::where('campaign_id', $campaignId)->get();

        return response()->json($adsets);
    }

    public function ads($adsetId)
    {
        $ads = Ad::where('adset_id', $adsetId)->get();

        return response()->json($ads);
    }
}