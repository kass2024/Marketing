<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Creative;
use App\Services\MetaAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdController extends Controller
{
    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /**
     * List Ads
     */
    public function index()
    {
        $ads = Ad::with(['adSet', 'creative'])
            ->latest()
            ->paginate(20);

        return view('admin.ads.index', compact('ads'));
    }

    /**
     * Show Create Form
     */
    public function create()
    {
        $adsets = AdSet::with('campaign.adAccount')->get();
        $creatives = Creative::all();

        return view('admin.ads.create', compact('adsets', 'creatives'));
    }

    /**
     * Store New Ad (Meta + Local DB)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ad_set_id'  => 'required|exists:ad_sets,id',
            'creative_id'=> 'required|exists:creatives,id',
            'name'       => 'required|string|max:255'
        ]);

        try {

            return DB::transaction(function () use ($validated) {

                $adset = AdSet::with('campaign.adAccount')
                    ->findOrFail($validated['ad_set_id']);

                $creative = Creative::findOrFail($validated['creative_id']);

                if (!$adset->meta_id) {
                    throw new \Exception('Selected AdSet is not synced with Meta.');
                }

                if (!$creative->meta_id) {
                    throw new \Exception('Selected Creative is not synced with Meta.');
                }

                if (!$adset->campaign || !$adset->campaign->adAccount) {
                    throw new \Exception('AdSet missing Campaign or AdAccount relation.');
                }

                $adAccountMetaId = $adset->campaign->adAccount->meta_id;

                if (!$adAccountMetaId) {
                    throw new \Exception('AdAccount is not synced with Meta.');
                }

                // Create Ad on Meta
                $response = $this->meta->createAd(
                    $adAccountMetaId,
                    [
                        'name' => $validated['name'],
                        'adset_id' => $adset->meta_id,
                        'creative' => [
                            'creative_id' => $creative->meta_id
                        ],
                        'status' => 'PAUSED'
                    ]
                );

                if (empty($response['id'])) {
                    throw new \Exception(
                        $response['error']['message'] ?? 'Meta Ad creation failed.'
                    );
                }

                // Save locally
                Ad::create([
                    'ad_set_id'  => $adset->id,
                    'creative_id'=> $creative->id,
                    'meta_id'    => $response['id'],
                    'name'       => $validated['name'],
                    'status'     => 'PAUSED'
                ]);

                return redirect()
                    ->route('admin.ads.index')
                    ->with('success', 'Ad created successfully.');
            });

        } catch (\Throwable $e) {

            Log::error('Ad creation failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'meta' => 'Ad creation failed: ' . $e->getMessage()
                ]);
        }
    }
}