<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    protected int $clientId;

    public function __construct(int $clientId)
    {
        $this->clientId = $clientId;
    }

    public function getKpis(): array
    {
        $campaigns = Campaign::where('client_id', $this->clientId);

        $totalCampaigns = $campaigns->count();
        $activeCampaigns = (clone $campaigns)->active()->count();
        $totalBudget = (clone $campaigns)->sum('budget');
        $totalSpend = (clone $campaigns)->sum('spend');
        $totalClicks = (clone $campaigns)->sum('clicks');
        $totalImpressions = (clone $campaigns)->sum('impressions');
        $totalLeads = (clone $campaigns)->sum('leads');

        $ctr = $totalImpressions > 0
            ? round(($totalClicks / $totalImpressions) * 100, 2)
            : 0;

        $conversionRate = $totalClicks > 0
            ? round(($totalLeads / $totalClicks) * 100, 2)
            : 0;

        return [
            'total_campaigns' => $totalCampaigns,
            'active_campaigns' => $activeCampaigns,
            'total_budget' => $totalBudget,
            'total_spend' => $totalSpend,
            'ctr' => $ctr,
            'conversion_rate' => $conversionRate,
        ];
    }


    public function getMonthlySpendTrend(): array
    {
        $data = Campaign::where('client_id', $this->clientId)
            ->select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(spend) as total_spend')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $months = [];
        $spend = [];

        foreach ($data as $row) {
            $months[] = date('M', mktime(0, 0, 0, $row->month, 1));
            $spend[] = (float) $row->total_spend;
        }

        return [
            'labels' => $months,
            'data' => $spend,
        ];
    }
}