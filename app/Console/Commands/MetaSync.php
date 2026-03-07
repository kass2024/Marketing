<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MetaAdsService;
use App\Models\AdAccount;
use App\Models\Campaign;
use App\Models\AdSet;
use App\Models\Ad;

class MetaSync extends Command
{
    protected $signature = 'meta:sync';

    protected $description = 'Sync Meta Ads data';

    protected $meta;

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();
        $this->meta = $meta;
    }

    public function handle()
    {
        $account = AdAccount::first();

        if (!$account) {
            $this->error('No Meta Ad Account connected.');
            return;
        }

        $accountId = $account->meta_id;

        $this->info("Syncing campaigns...");

        $campaigns = $this->meta->getCampaigns($accountId);

        foreach ($campaigns['data'] ?? [] as $metaCampaign) {

            Campaign::updateOrCreate(
                ['meta_id' => $metaCampaign['id']],
                [
                    'name' => $metaCampaign['name'],
                    'status' => $metaCampaign['status'],
                    'objective' => $metaCampaign['objective']
                ]
            );
        }

        $this->info("Syncing adsets...");

        $adsets = $this->meta->getAdSets($accountId);

        foreach ($adsets['data'] ?? [] as $metaAdset) {

            $campaign = Campaign::where('meta_id',$metaAdset['campaign_id'])->first();

            if(!$campaign) continue;

            AdSet::updateOrCreate(
                ['meta_id'=>$metaAdset['id']],
                [
                    'campaign_id'=>$campaign->id,
                    'name'=>$metaAdset['name'],
                    'status'=>$metaAdset['status'],
                    'daily_budget'=>$metaAdset['daily_budget']
                ]
            );
        }

        $this->info("Syncing ads...");

        $ads = $this->meta->getAds($accountId);

        foreach ($ads['data'] ?? [] as $metaAd) {

            $adset = AdSet::where('meta_id',$metaAd['adset_id'])->first();

            if(!$adset) continue;

            Ad::updateOrCreate(
                ['meta_ad_id'=>$metaAd['id']],
                [
                    'adset_id'=>$adset->id,
                    'name'=>$metaAd['name'],
                    'status'=>$metaAd['status']
                ]
            );
        }

        $this->info("Syncing insights...");

        foreach (Ad::whereNotNull('meta_ad_id')->get() as $ad) {

            $insights = $this->meta->getInsights($ad->meta_ad_id);

            if(empty($insights['data'][0])) continue;

            $data = $insights['data'][0];

            $ad->update([
                'impressions'=>$data['impressions'] ?? 0,
                'clicks'=>$data['clicks'] ?? 0,
                'spend'=>$data['spend'] ?? 0
            ]);
        }

        $this->info("Meta sync completed.");
    }
}