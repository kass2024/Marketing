public function handle(): int
{
    $this->info('Starting Meta Campaign Sync...');

    \Log::info('Meta Campaign Sync Started', [
        'time' => now()->toDateTimeString()
    ]);

    try {

        $service = app(\App\Services\MetaAdsService::class);
        $response = $service->getCampaigns();

        if (!isset($response['data']) || !is_array($response['data']) || count($response['data']) === 0) {

            $this->warn('Meta returned no campaigns.');

            \Log::warning('Meta Campaign Sync: empty response');

            return Command::SUCCESS;
        }

        $count = 0;

        foreach ($response['data'] as $campaign) {

            if (empty($campaign['id'])) {
                continue;
            }

            $metaId = $campaign['id'];

            $record = \App\Models\Campaign::updateOrCreate(
                [
                    'meta_id' => $metaId
                ],
                [
                    'name' => $campaign['name'] ?? 'Unnamed Campaign',
                    'status' => $campaign['status'] ?? 'UNKNOWN',
                    'objective' => $campaign['objective'] ?? null
                ]
            );

            $count++;

            $this->line("Synced: {$record->name}");

            \Log::info('Meta Campaign Synced', [
                'meta_id' => $metaId,
                'name' => $record->name,
                'status' => $record->status
            ]);
        }

        $this->info("Meta Campaign Sync Completed ({$count} campaigns)");

        \Log::info('Meta Campaign Sync Completed', [
            'count' => $count
        ]);

        return Command::SUCCESS;

    } catch (\Throwable $e) {

        \Log::error('Meta Campaign Sync Failed', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        $this->error('Meta Campaign Sync Failed: '.$e->getMessage());

        return Command::FAILURE;
    }
}