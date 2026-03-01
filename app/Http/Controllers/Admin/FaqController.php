<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\Chatbot\EmbeddingService;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = KnowledgeBase::latest()->paginate(20);
        return view('admin.faq.index', compact('faqs'));
    }

    public function create()
    {
        return view('admin.faq.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:1000',
            'answer'   => 'required|string',
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
                'type' => $request->file('attachment')->extension(),
                'file_path' => $path,
                'url' => Storage::url($path),
            ]);
        }

        return redirect()
            ->route('admin.faq.index')
            ->with('success', 'FAQ created successfully.');
    }
}