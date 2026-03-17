<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KnowledgeBase;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Log;

class GenerateKnowledgeEmbeddings extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'embeddings:generate';

    /**
     * The console command description.
     */
    protected $description = 'Generate embeddings for knowledge base items';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $items = KnowledgeBase::whereNull('embedding')->get();

        Log::info('Embedding generation started', [
            'total_items' => $items->count()
        ]);

        foreach ($items as $item) {

            try {

                $text = trim(($item->question ?? '') . ' ' . ($item->answer ?? ''));

                if (empty($text)) {
                    Log::warning('Skipping empty knowledge item', [
                        'id' => $item->id
                    ]);
                    continue;
                }

                $vector = app(EmbeddingService::class)->generate($text);

                if ($vector) {

                    $item->update([
                        'embedding' => json_encode($vector)
                    ]);

                    Log::info('Embedding generated', [
                        'id' => $item->id
                    ]);

                } else {

                    Log::warning('Embedding failed (empty vector)', [
                        'id' => $item->id
                    ]);
                }

            } catch (\Throwable $e) {

                Log::error('Embedding exception', [
                    'id' => $item->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Embedding generation completed');

        $this->info('Embeddings generated successfully.');
    }
}