<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\Chatbot\EmbeddingService;

class FaqController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $faqs = KnowledgeBase::with('attachments')
            ->latest()
            ->paginate(20);

        return view('admin.faq.index', compact('faqs'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        return view('admin.faq.create');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'question'   => 'required|string|max:1000',
            'answer'     => 'required|string',
            'attachment' => 'nullable|file|max:5120'
        ]);

        $faq = KnowledgeBase::create([
            'client_id'   => auth()->user()->client_id ?? 1,
            'question'    => $request->question,
            'answer'      => $request->answer,
            'intent_type' => 'faq',
            'priority'    => 0,
            'is_active'   => true,
        ]);

        // Generate embedding
        $faq->embedding = app(EmbeddingService::class)
            ->generate($faq->question . ' ' . $faq->answer);

        $faq->save();

        // Optional attachment
        if ($request->hasFile('attachment')) {

            $path = $request->file('attachment')
                ->store('faq_attachments', 'public');

            $faq->attachments()->create([
                'type'      => $request->file('attachment')->extension(),
                'file_path' => $path,
                'url'       => Storage::url($path),
            ]);
        }

        return redirect()
            ->route('admin.faq.index')
            ->with('success', 'FAQ created successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */
    public function edit(KnowledgeBase $faq)
    {
        $faq->load('attachments');

        return view('admin.faq.edit', compact('faq'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, KnowledgeBase $faq)
    {
        $request->validate([
            'question'   => 'required|string|max:1000',
            'answer'     => 'required|string',
            'attachment' => 'nullable|file|max:5120'
        ]);

        $faq->update([
            'question'  => $request->question,
            'answer'    => $request->answer,
            'is_active' => $request->has('is_active')
        ]);

        // Regenerate embedding after update
        $faq->embedding = app(EmbeddingService::class)
            ->generate($faq->question . ' ' . $faq->answer);

        $faq->save();

        // New attachment (optional)
        if ($request->hasFile('attachment')) {

            $path = $request->file('attachment')
                ->store('faq_attachments', 'public');

            $faq->attachments()->create([
                'type'      => $request->file('attachment')->extension(),
                'file_path' => $path,
                'url'       => Storage::url($path),
            ]);
        }

        return redirect()
            ->route('admin.faq.index')
            ->with('success', 'FAQ updated successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */
    public function destroy(KnowledgeBase $faq)
    {
        // Delete attachments from storage
        foreach ($faq->attachments as $attachment) {

            if ($attachment->file_path) {
                Storage::disk('public')
                    ->delete($attachment->file_path);
            }

            $attachment->delete();
        }

        $faq->delete();

        return redirect()
            ->route('admin.faq.index')
            ->with('success', 'FAQ deleted successfully.');
    }
}