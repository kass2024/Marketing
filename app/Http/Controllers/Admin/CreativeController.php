<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Creative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CreativeController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | List Creatives
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $creatives = Creative::latest()->paginate(20);

        return view('admin.creatives.index', compact('creatives'));
    }


    /*
    |--------------------------------------------------------------------------
    | Show Create Form
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        return view('admin.creatives.create');
    }


    /*
    |--------------------------------------------------------------------------
    | Store Creative
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'headline'        => 'nullable|string|max:255',
            'body'            => 'nullable|string',
            'call_to_action'  => 'nullable|string|max:100',
            'destination_url' => 'nullable|url',
            'image'           => 'nullable|image|max:2048'
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {

            $imagePath = $request->file('image')->store(
                'creatives',
                'public'
            );
        }

        Creative::create([

            'name'            => $data['name'],
            'title'           => $data['headline'] ?? null,
            'body'            => $data['body'] ?? null,
            'call_to_action'  => $data['call_to_action'] ?? null,
            'destination_url' => $data['destination_url'] ?? null,
            'image_url'       => $imagePath,
            'status'          => Creative::STATUS_DRAFT
        ]);

        return redirect()
            ->route('admin.creatives.index')
            ->with('success', 'Creative created successfully');
    }


    /*
    |--------------------------------------------------------------------------
    | Edit Creative
    |--------------------------------------------------------------------------
    */

    public function edit(Creative $creative)
    {
        return view('admin.creatives.edit', compact('creative'));
    }


    /*
    |--------------------------------------------------------------------------
    | Update Creative
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
            'image'           => 'nullable|image|max:2048'
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

            'name'            => $data['name'],
            'title'           => $data['headline'] ?? null,
            'body'            => $data['body'] ?? null,
            'call_to_action'  => $data['call_to_action'] ?? null,
            'destination_url' => $data['destination_url'] ?? null
        ]);

        return redirect()
            ->route('admin.creatives.index')
            ->with('success', 'Creative updated successfully');
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Creative
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
public function preview(Creative $creative)
{
    return view('admin.creatives.preview', [
        'creative' => $creative
    ]);
}

}