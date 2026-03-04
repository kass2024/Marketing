public function handle()
{
    $this->info('Starting Meta Campaign Sync...');

    \Log::info('Meta Campaign Sync Started', [
        'timestamp' => now()->toDateTimeString()
    ]);

    try {

        $service = app(\App\Services\MetaAdsService::class);

        $response = $service->getCampaigns();

        if (!isset($response['data']) || empty($response['data'])) {

            $this->warn('No campaigns returned from Meta.');

            \Log::warning('Meta Campaign API returned empty list');

            return Command::SUCCESS;
        }

        $count = 0;

        foreach ($response['data'] as $campaign) {

            if (empty($campaign['id'])) {
                continue;
            }

            $metaId = $campaign['id'];

            $record = \App\Models\Campaign::updateOrCreate(

                ['meta_id' => $metaId],

                [
                    'name' => $campaign['name'] ?? 'Unnamed Campaign',
                    'status' => $campaign['status'] ?? 'UNKNOWN',
                    'objective' => $campaign['objective'] ?? null
                ]
            );

            $count++;

            \Log::info('Meta Campaign Synced', [
                'meta_id' => $metaId,
                'name' => $record->name,
                'status' => $record->status
            ]);
        }

        $this->info("Meta Campaign Sync Completed: {$count} campaigns updated.");

        \Log::info('Meta Campaign Sync Completed', [
            'synced_campaigns' => $count
        ]);

        return Command::SUCCESS;

    } catch (\Throwable $e) {

        \Log::error('Meta Campaign Sync Failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        $this->error('Meta Campaign Sync Failed: ' . $e->getMessage());

        return Command::FAILURE;
    }
}