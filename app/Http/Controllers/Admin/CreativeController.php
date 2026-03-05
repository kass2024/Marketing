<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Creative;
use Illuminate\Http\Request;

class CreativeController extends Controller
{

    public function index()
    {
        $creatives = Creative::latest()->paginate(20);

        return view('admin.creatives.index', compact('creatives'));
    }


    public function create()
    {
        return view('admin.creatives.create');
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'headline' => 'nullable|string',
            'body' => 'nullable|string',
            'image_url' => 'nullable|string'
        ]);

        Creative::create($data);

        return redirect()->route('admin.creatives.index')
            ->with('success','Creative created');
    }


    public function edit(Creative $creative)
    {
        return view('admin.creatives.edit', compact('creative'));
    }


    public function update(Request $request, Creative $creative)
    {
        $creative->update($request->all());

        return redirect()->route('admin.creatives.index');
    }


    public function destroy(Creative $creative)
    {
        $creative->delete();

        return back();
    }

}