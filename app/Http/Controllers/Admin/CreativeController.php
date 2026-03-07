<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Creative;
use App\Models\AdAccount;
use App\Services\MetaAdsService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use Throwable;
use Exception;

class CreativeController extends Controller
{
    protected MetaAdsService $meta;

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
        Log::info('CREATIVE_STORE_REQUEST', $request->all());

        $data = $request->validate([

            'name' => 'required|string|max:255',

            'headline' => 'required|string|max:255',

            'body' => 'required|string',

            'destination_url' => 'required|url',

            'call_to_action' => 'required|string|max:50',

            'image' => 'required|image|max:4096',

            'sync_meta' => 'nullable|boolean'
        ]);

        DB::beginTransaction();

        try {

            $imagePath = null;
            $metaCreativeId = null;
            $imageHash = null;
            $payload = [];

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
            | META SYNC
            |--------------------------------------------------------------------------
            */

            if ($request->boolean('sync_meta')) {

                $account = AdAccount::whereNotNull('meta_id')->first();

                if (!$account) {
                    throw new Exception('Meta Ad Account not connected.');
                }

                $accountId = $account->meta_id;

                if (!str_starts_with($accountId, 'act_')) {
                    $accountId = 'act_' . $accountId;
                }

                /*
                |--------------------------------------------------------------------------
                | UPLOAD IMAGE TO META
                |--------------------------------------------------------------------------
                */

                if ($imagePath) {

                    $imageFullPath = storage_path('app/public/' . $imagePath);

                    $imageResponse = $this->meta->uploadImage(
                        $accountId,
                        $imageFullPath
                    );

                    if (!isset($imageResponse['images'])) {
                        throw new Exception('Meta image upload failed.');
                    }

                    $imageHash = array_key_first($imageResponse['images']);
                }

                /*
                |--------------------------------------------------------------------------
                | BUILD CREATIVE PAYLOAD
                |--------------------------------------------------------------------------
                */

                $payload = [

                    'name' => $data['name'],

                    'object_story_spec' => [

                        'page_id' => config('services.meta.page_id'),

                        'link_data' => [

                            'message' => $data['body'],

                            'link' => $data['destination_url'],

                            'name' => $data['headline'],

                            'image_hash' => $imageHash,

                            'call_to_action' => [
                                'type' => $data['call_to_action']
                            ]
                        ]
                    ]
                ];

                Log::info('META_CREATIVE_PAYLOAD', $payload);

                $response = $this->meta->createCreative(
                    $accountId,
                    $payload
                );

                Log::info('META_CREATIVE_RESPONSE', $response);

                if (!isset($response['id'])) {

                    throw new Exception(
                        $response['error']['message']
                        ?? 'Meta creative creation failed.'
                    );
                }

                $metaCreativeId = $response['id'];
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE CREATIVE LOCALLY
            |--------------------------------------------------------------------------
            */

            $creative = Creative::create([

                'name' => $data['name'],

                'headline' => $data['headline'],

                'body' => $data['body'],

                'destination_url' => $data['destination_url'],

                'call_to_action' => $data['call_to_action'],

                'image_url' => $imagePath,

                'image_hash' => $imageHash,

                'meta_id' => $metaCreativeId,

                'json_payload' => $payload,

                'status' => Creative::STATUS_DRAFT
            ]);

            DB::commit();

            Log::info('CREATIVE_CREATED', [
                'creative_id' => $creative->id,
                'meta_id' => $metaCreativeId
            ]);

            return redirect()
                ->route('admin.creatives.index')
                ->with('success', 'Creative created successfully.');

        } catch (Throwable $e) {

            DB::rollBack();

            Log::error('CREATIVE_STORE_FAILED', [
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
    | EDIT CREATIVE
    |--------------------------------------------------------------------------
    */

    public function edit(Creative $creative)
    {
        return view('admin.creatives.edit', compact('creative'));
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE CREATIVE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, Creative $creative)
    {
        $data = $request->validate([

            'name' => 'required|string|max:255',

            'headline' => 'required|string|max:255',

            'body' => 'required|string',

            'destination_url' => 'required|url',

            'call_to_action' => 'required|string|max:50',

            'image' => 'nullable|image|max:4096'
        ]);

        try {

            if ($request->hasFile('image')) {

                if ($creative->image_url) {
                    Storage::disk('public')->delete($creative->image_url);
                }

                $creative->image_url = $request->file('image')
                    ->store('creatives', 'public');
            }

            $creative->update([

                'name' => $data['name'],

                'headline' => $data['headline'],

                'body' => $data['body'],

                'destination_url' => $data['destination_url'],

                'call_to_action' => $data['call_to_action']
            ]);

            return redirect()
                ->route('admin.creatives.index')
                ->with('success', 'Creative updated successfully.');

        } catch (Throwable $e) {

            Log::error('CREATIVE_UPDATE_FAILED', [
                'error' => $e->getMessage()
            ]);

            return back()
                ->withErrors(['meta' => 'Unable to update creative']);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE CREATIVE
    |--------------------------------------------------------------------------
    */

    public function destroy(Creative $creative)
    {
        try {

            if ($creative->image_url) {
                Storage::disk('public')->delete($creative->image_url);
            }

            $creative->delete();

            return back()->with('success', 'Creative deleted.');

        } catch (Throwable $e) {

            Log::error('CREATIVE_DELETE_FAILED', [
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'meta' => 'Unable to delete creative.'
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | PREVIEW CREATIVE
    |--------------------------------------------------------------------------
    */

    public function preview(Creative $creative)
    {
        return view('admin.creatives.preview', [
            'creative' => $creative
        ]);
    }
}