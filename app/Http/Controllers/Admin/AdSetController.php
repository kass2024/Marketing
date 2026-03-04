<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Campaign;
use App\Models\AdSet;
use App\Services\MetaAdsService;

class AdSetController extends Controller
{
    public function create($campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);
        return view('admin.adsets.create', compact('campaign'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'name' => 'required|string|max:255',
            'daily_budget' => 'required|numeric|min:5',
            'age_min' => 'required|integer|min:18|max:65',
            'age_max' => 'required|integer|min:18|max:65',
            'countries' => 'required|array|min:1'
        ]);

        if($request->age_min >= $request->age_max)
        {
            return back()->withErrors(['age' => 'Age max must be greater than age min']);
        }

        $campaign = Campaign::findOrFail($request->campaign_id);

        $targeting = [
            'age_min' => $request->age_min,
            'age_max' => $request->age_max,
            'geo_locations' => [
                'countries' => $request->countries
            ],
            'publisher_platforms' => ['facebook','instagram'],
        ];

        if($request->genders)
        {
            $targeting['genders'] = $request->genders;
        }

        $service = new MetaAdsService();

        $response = $service->createAdSet(
            $campaign->adAccount->meta_id,
            [
                'name' => $request->name,
                'campaign_id' => $campaign->meta_id,
                'daily_budget' => $request->daily_budget * 100,
                'billing_event' => 'IMPRESSIONS',
                'optimization_goal' => 'REACH',
                'targeting' => $targeting,
                'status' => 'PAUSED'
            ]
        );

        if(isset($response['id']))
        {
            AdSet::create([
                'campaign_id' => $campaign->id,
                'meta_id' => $response['id'],
                'name' => $request->name,
                'daily_budget' => $request->daily_budget * 100,
                'optimization_goal' => 'REACH',
                'billing_event' => 'IMPRESSIONS',
                'targeting' => json_encode($targeting),
                'status' => 'PAUSED'
            ]);

            return redirect()->route('admin.campaigns.index')
                ->with('success','Ad Set Created Successfully');
        }

        return back()->withErrors($response);
    }
}