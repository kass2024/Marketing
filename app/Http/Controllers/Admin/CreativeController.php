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
    | LIST
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $creatives = Creative::latest()->paginate(20);

        return view('admin.creatives.index', compact('creatives'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        return view('admin.creatives.create');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        Log::info('CREATIVE_STORE_REQUEST', $request->all());

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'headline' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'destination_url' => 'nullable|url',
            'call_to_action' => 'nullable|string|max:50',
            'image' => 'nullable|image|max:4096',
            'sync_meta' => 'nullable|boolean'
        ]);

        DB::beginTransaction();

        try {

            $imagePath = null;
            $imageHash = null;
            $metaCreativeId = null;
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

                    // FIXED: extract actual hash
                    $image = current($imageResponse['images']);

                    if (!isset($image['hash'])) {
                        throw new Exception('Meta did not return image hash.');
                    }

                    $imageHash = $image['hash'];
                }

                /*
                |--------------------------------------------------------------------------
                | BUILD LINK DATA
                |--------------------------------------------------------------------------
                */

                $linkData = [];

                if (!empty($data['headline'])) {
                    $linkData['name'] = $data['headline'];
                }

                if (!empty($data['body'])) {
                    $linkData['message'] = $data['body'];
                }

                if (!empty($data['destination_url'])) {
                    $linkData['link'] = $data['destination_url'];
                }

                if ($imageHash) {
                    $linkData['image_hash'] = $imageHash;
                }

                if (!empty($data['call_to_action'])) {

                    $cta = [
                        'type' => $data['call_to_action']
                    ];

                    if (!empty($data['destination_url'])) {
                        $cta['value'] = [
                            'link' => $data['destination_url']
                        ];
                    }

                    $linkData['call_to_action'] = $cta;
                }

                /*
                |--------------------------------------------------------------------------
                | BUILD PAYLOAD
                |--------------------------------------------------------------------------
                */

                $payload = [

                    'name' => $data['name'],

                    'object_story_spec' => [

                        'page_id' => config('services.meta.page_id'),

                        'link_data' => $linkData
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
            | SAVE LOCAL
            |--------------------------------------------------------------------------
            */

            $creative = Creative::create([

                'name' => $data['name'],

                'headline' => $data['headline'] ?? null,

                'body' => $data['body'] ?? null,

                'destination_url' => $data['destination_url'] ?? null,

                'call_to_action' => $data['call_to_action'] ?? null,

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
            'name' => 'required|string|max:255',
            'headline' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'destination_url' => 'nullable|url',
            'call_to_action' => 'nullable|string|max:50',
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

            $creative->update($data);

            return redirect()
                ->route('admin.creatives.index')
                ->with('success', 'Creative updated successfully.');

        } catch (Throwable $e) {

            Log::error('CREATIVE_UPDATE_FAILED', [
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'meta' => 'Unable to update creative'
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
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
    | PREVIEW
    |--------------------------------------------------------------------------
    */

    public function preview(Creative $creative)
    {
        return view('admin.creatives.preview', compact('creative'));
    }
}