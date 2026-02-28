<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    /**
     * Display list of authenticated client's campaigns.
     */
    public function index()
    {
        $client = auth()->user()->client;

        abort_if(!$client, 403);

        $campaigns = Campaign::where('client_id', $client->id)
            ->latest()
            ->paginate(12);

        return view('client.campaigns.index', compact('campaigns'));
    }


    /**
     * Show create form.
     */
    public function create()
    {
        return view('client.campaigns.create');
    }


    /**
     * Store new campaign.
     */
    public function store(StoreCampaignRequest $request)
    {
        $client = auth()->user()->client;

        abort_if(!$client, 403);

        DB::transaction(function () use ($client, $request) {

            $client->campaigns()->create([
                'name'        => $request->name,
                'objective'   => $request->objective,
                'budget'      => $request->budget,
                'start_date'  => $request->start_date,
                'end_date'    => $request->end_date,
                'status'      => 'draft',
            ]);
        });

        return redirect()
            ->route('client.campaigns.index')
            ->with('success', 'Campaign created successfully.');
    }


    /**
     * Display campaign.
     */
    public function show($id)
    {
        $campaign = $this->findClientCampaign($id);

        return view('client.campaigns.show', compact('campaign'));
    }


    /**
     * Show edit form.
     */
    public function edit($id)
    {
        $campaign = $this->findClientCampaign($id);

        return view('client.campaigns.edit', compact('campaign'));
    }


    /**
     * Update campaign.
     */
    public function update(UpdateCampaignRequest $request, $id)
    {
        $campaign = $this->findClientCampaign($id);

        DB::transaction(function () use ($campaign, $request) {
            $campaign->update($request->validated());
        });

        return redirect()
            ->route('client.campaigns.index')
            ->with('success', 'Campaign updated successfully.');
    }


    /**
     * Delete campaign.
     */
    public function destroy($id)
    {
        $campaign = $this->findClientCampaign($id);

        $campaign->delete();

        return redirect()
            ->route('client.campaigns.index')
            ->with('success', 'Campaign deleted successfully.');
    }


    /**
     * Activate campaign.
     */
    public function activate($id)
    {
        $campaign = $this->findClientCampaign($id);

        if ($campaign->status === 'active') {
            return back()->with('info', 'Campaign already active.');
        }

        $campaign->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);

        return back()->with('success', 'Campaign activated successfully.');
    }


    /**
     * Pause campaign.
     */
    public function pause($id)
    {
        $campaign = $this->findClientCampaign($id);

        if ($campaign->status === 'paused') {
            return back()->with('info', 'Campaign already paused.');
        }

        $campaign->update([
            'status' => 'paused',
        ]);

        return back()->with('success', 'Campaign paused successfully.');
    }


    /**
     * Ensure campaign belongs to authenticated client.
     */
    protected function findClientCampaign($id): Campaign
    {
        $client = auth()->user()->client;

        abort_if(!$client, 403);

        return Campaign::where('client_id', $client->id)
            ->where('id', $id)
            ->firstOrFail();
    }
}