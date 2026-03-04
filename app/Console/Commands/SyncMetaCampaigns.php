public function handle()
{
    $this->info('Starting Meta Campaign Sync...');

    \Log::info('Meta Campaign Sync Started');

    try {

        $service = app(\App\Services\MetaAdsService::class);

        $response = $service->getCampaigns();

        if (empty($response['data'])) {

            $this->warn('No campaigns returned from Meta.');

            \Log::warning('Meta Campaign API returned empty list');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($response['data'] as $campaign) {

            $metaId = $campaign['id'] ?? null;

            if (!$metaId) {
                continue;
            }

            $record = \App\Models\Campaign::updateOrCreate(

                ['meta_id' => $metaId],

                [
                    'name' => $campaign['name'] ?? 'Unnamed Campaign',
                    'status' => $campaign['status'] ?? 'UNKNOWN',
                    'objective' => $campaign['objective'] ?? null
                ]
            );

            $count++;

            \Log::info('Campaign Synced', [
                'meta_id' => $metaId,
                'name' => $record->name,
                'status' => $record->status
            ]);
        }

        $this->info("Synced {$count} campaigns successfully.");

        \Log::info("Meta Campaign Sync Completed ({$count})");

        return self::SUCCESS;

    } catch (\Throwable $e) {

        \Log::error('Meta Campaign Sync Failed', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        $this->error('Meta Campaign sync failed: '.$e->getMessage());

        return self::FAILURE;
    }
}