<?php

namespace App\Imports;

use App\Models\KnowledgeBase;
use App\Services\Chatbot\EmbeddingService;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Auth;

class FaqImport implements ToModel, WithHeadingRow
{
    protected $clientId;

    public function __construct()
    {
        $this->clientId = Auth::user()->client_id ?? 1;
    }

    public function model(array $row)
    {
        // Skip empty rows
        if (empty($row['question']) || empty($row['answer'])) {
            return null;
        }

        $faq = KnowledgeBase::create([
            'client_id'   => $this->clientId,
            'question'    => trim($row['question']),
            'answer'      => trim($row['answer']),
            'intent_type' => 'faq',
            'priority'    => 0,
            'is_active'   => isset($row['is_active'])
                ? (bool) $row['is_active']
                : true,
        ]);

        // Generate embedding
        $vector = app(EmbeddingService::class)
            ->generate($faq->question . ' ' . $faq->answer);

        if ($vector) {
            $faq->update([
                'embedding' => json_encode($vector)
            ]);
        }

        return $faq;
    }
}