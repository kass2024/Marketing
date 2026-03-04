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
    public function index()
    {
        $ads = Ad::with('adSet')->latest()->get();
        return view('admin.ads.index', compact('ads'));
    }

    public function create()
    {
        $adsets = AdSet::all();
        $creatives = Creative::all();
        return view('admin.ads.create', compact('adsets','creatives'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'ad_set_id' => 'required|exists:ad_sets,id',
            'creative_id' => 'required|exists:creatives,id',
            'name' => 'required|string|max:255'
        ]);

        try {

            DB::beginTransaction();

            $adset = AdSet::findOrFail($request->ad_set_id);
            $creative = Creative::findOrFail($request->creative_id);

            $service = new MetaAdsService();

            $response = $service->createAd(
                $adset->campaign->adAccount->meta_id,
                [
                    'name' => $request->name,
                    'adset_id' => $adset->meta_id,
                    'creative' => ['creative_id' => $creative->meta_id],
                    'status' => 'PAUSED'
                ]
            );

            if (!isset($response['id'])) {
                throw new \Exception('Meta Ad creation failed');
            }

            Ad::create([
                'ad_set_id' => $adset->id,
                'creative_id' => $creative->id,
                'meta_id' => $response['id'],
                'name' => $request->name,
                'status' => 'PAUSED'
            ]);

            DB::commit();

            return redirect()->route('admin.ads.index')
                ->with('success','Ad created successfully.');

        } catch (\Exception $e) {

            DB::rollBack();
            Log::error('Ad creation failed', ['error'=>$e->getMessage()]);
            return back()->withErrors(['error'=>'Ad creation failed.']);
        }
    }
}