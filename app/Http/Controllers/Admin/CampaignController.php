<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Campaign;
use App\Models\AdAccount;
use App\Services\MetaAdsService;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::latest()->get();
        return view('admin.campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        return view('admin.campaigns.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'objective' => 'required|string',
            'daily_budget' => 'required|numeric|min:5'
        ]);

        $account = AdAccount::first(); // single business

        $service = new MetaAdsService();

        $response = $service->createCampaign(
            $account->meta_id,
            [
                'name' => $request->name,
                'objective' => $request->objective,
                'daily_budget' => $request->daily_budget * 100,
                'status' => 'PAUSED'
            ]
        );

        if(isset($response['id']))
        {
            Campaign::create([
                'ad_account_id' => $account->id,
                'meta_id' => $response['id'],
                'name' => $request->name,
                'objective' => $request->objective,
                'daily_budget' => $request->daily_budget * 100,
                'status' => 'PAUSED'
            ]);

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success','Campaign Created Successfully');
        }

        return back()->withErrors($response);
    }
}