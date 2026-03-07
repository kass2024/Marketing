<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Creative;
use App\Models\AdAccount;
use App\Services\MetaAdsService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use Throwable;
use Exception;

class CreativeController extends Controller
{
    protected $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | LIST CREATIVES
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $creatives = Creative::latest()->paginate(20);

        return view('admin.creatives.index', compact('creatives'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE FORM
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        return view('admin.creatives.create');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE CREATIVE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        Log::info('META_CREATIVE_STORE_REQUEST', $request->all());

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'headline'        => 'nullable|string|max:255',
            'body'            => 'nullable|string',
            'call_to_action'  => 'nullable|string|max:100',
            'destination_url' => 'nullable|url',
            'image'           => 'nullable|image|max:4096',
            'sync_meta'       => 'nullable'
        ]);

        DB::beginTransaction();

        try {

            $imagePath = null;
            $metaCreativeId = null;
            $imageHash = null;

            /*
            |--------------------------------------------------------------------------
            | STORE IMAGE LOCALLY
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('image')) {

                $imagePath = $request->file('image')->store(
                    'creatives',
                    'public'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SYNC WITH META
            |--------------------------------------------------------------------------
            */

            if ($request->has('sync_meta')) {

                $account = AdAccount::whereNotNull('meta_id')->first();

                if (!$account) {
                    throw new Exception('Meta Ad Account not connected.');
                }

                $metaAccountId = $account->meta_id;

                if (strpos($metaAccountId, 'act_') !== 0) {
                    $metaAccountId = 'act_' . $metaAccountId;
                }

                /*
                |--------------------------------------------------------------------------
                | UPLOAD IMAGE TO META
                |--------------------------------------------------------------------------
                */

                if ($imagePath) {

                    $imageFullPath = storage_path('app/public/' . $imagePath);

                    $imageResponse = $this->meta->uploadImage(
                        $metaAccountId,
                        $imageFullPath
                    );

                    if (!isset($imageResponse['images'])) {
                        throw new Exception('Meta image upload failed.');
                    }

                    $imageHash = array_key_first($imageResponse['images']);
                }

                /*
                |--------------------------------------------------------------------------
                | CREATE CREATIVE ON META
                |--------------------------------------------------------------------------
                */

                $payload = [

                    'name' => $data['name'],

                    'object_story_spec' => [

                        'link_data' => [

                            'message' => $data['body'] ?? '',

                            'link' => $data['destination_url'] ?? url('/'),

                            'call_to_action' => [
                                'type' => $data['call_to_action'] ?? 'LEARN_MORE'
                            ],

                            'name' => $data['headline'] ?? $data['name'],

                            'image_hash' => $imageHash
                        ],

                        'page_id' => config('meta.default_page_id')
                    ]
                ];

                Log::info('META_CREATIVE_PAYLOAD', $payload);

                $creativeResponse = $this->meta->createCreative(
                    $metaAccountId,
                    $payload
                );

                Log::info('META_CREATIVE_RESPONSE', $creativeResponse);

                if (!isset($creativeResponse['id'])) {

                    throw new Exception(
                        $creativeResponse['error']['message']
                        ?? 'Meta Creative creation failed.'
                    );
                }

                $metaCreativeId = $creativeResponse['id'];
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE LOCALLY
            |--------------------------------------------------------------------------
            */

            $creative = Creative::create([

                'name'            => $data['name'],
                'title'           => $data['headline'] ?? null,
                'body'            => $data['body'] ?? null,
                'call_to_action'  => $data['call_to_action'] ?? null,
                'destination_url' => $data['destination_url'] ?? null,

                'image_url'       => $imagePath,

                'meta_id'         => $metaCreativeId,

                'status'          => Creative::STATUS_DRAFT
            ]);

            DB::commit();

            Log::info('META_CREATIVE_CREATED', [
                'creative_id' => $creative->id,
                'meta_id' => $metaCreativeId
            ]);

            return redirect()
                ->route('admin.creatives.index')
                ->with('success', 'Creative created successfully');

        } catch (Throwable $e) {

            DB::rollBack();

            Log::error('META_CREATIVE_FAILED', [
                'error' => $e->getMessage()
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'meta' => $e->getMessage()
                ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit(Creative $creative)
    {
        return view('admin.creatives.edit', compact('creative'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, Creative $creative)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'headline'        => 'nullable|string|max:255',
            'body'            => 'nullable|string',
            'call_to_action'  => 'nullable|string|max:100',
            'destination_url' => 'nullable|url',
            'image'           => 'nullable|image|max:4096'
        ]);

        if ($request->hasFile('image')) {

            if ($creative->image_url) {
                Storage::disk('public')->delete($creative->image_url);
            }

            $creative->image_url = $request
                ->file('image')
                ->store('creatives', 'public');
        }

        $creative->update([

            'name' => $data['name'],
            'title' => $data['headline'] ?? null,
            'body' => $data['body'] ?? null,
            'call_to_action' => $data['call_to_action'] ?? null,
            'destination_url' => $data['destination_url'] ?? null
        ]);

        return redirect()
            ->route('admin.creatives.index')
            ->with('success', 'Creative updated successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy(Creative $creative)
    {
        if ($creative->image_url) {
            Storage::disk('public')->delete($creative->image_url);
        }

        $creative->delete();

        return back()->with('success', 'Creative deleted');
    }

    /*
    |--------------------------------------------------------------------------
    | PREVIEW
    |--------------------------------------------------------------------------
    */

    public function preview(Creative $creative)
    {
        return view('admin.creatives.preview', [
            'creative' => $creative
        ]);
    }
}