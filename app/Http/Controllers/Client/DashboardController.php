<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display client dashboard with real metrics.
     */
    public function index()
    {
        $user = auth()->user();
        $client = $user->client;

        abort_if(!$client, 403);

        /*
        |--------------------------------------------------------------------------
        | Base Campaign Query (Multi-tenant safe)
        |--------------------------------------------------------------------------
        */
        $campaigns = Campaign::where('client_id', $client->id);

        /*
        |--------------------------------------------------------------------------
        | KPI Aggregation
        |--------------------------------------------------------------------------
        */
        $totalCampaigns   = (clone $campaigns)->count();
        $activeCampaigns  = (clone $campaigns)->where('status', 'active')->count();
        $totalBudget      = (clone $campaigns)->sum('budget');
        $totalSpend       = (clone $campaigns)->sum('spend');
        $totalImpressions = (clone $campaigns)->sum('impressions');
        $totalClicks      = (clone $campaigns)->sum('clicks');
        $totalLeads       = (clone $campaigns)->sum('leads');

        /*
        |--------------------------------------------------------------------------
        | Performance Metrics
        |--------------------------------------------------------------------------
        */
        $ctr = $totalImpressions > 0
            ? round(($totalClicks / $totalImpressions) * 100, 2)
            : 0;

        $conversionRate = $totalClicks > 0
            ? round(($totalLeads / $totalClicks) * 100, 2)
            : 0;

        $cpc = $totalClicks > 0
            ? round(($totalSpend / $totalClicks), 2)
            : 0;

        $cpa = $totalLeads > 0
            ? round(($totalSpend / $totalLeads), 2)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Monthly Spend Trend
        |--------------------------------------------------------------------------
        */
        $monthlyData = Campaign::where('client_id', $client->id)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%b") as month'),
                DB::raw('SUM(spend) as total_spend')
            )
            ->groupBy('month')
            ->orderByRaw('MIN(created_at)')
            ->get();

        $trendLabels = $monthlyData->pluck('month');
        $trendSpend  = $monthlyData->pluck('total_spend');

        /*
        |--------------------------------------------------------------------------
        | Pack Dashboard Data
        |--------------------------------------------------------------------------
        */
        $kpis = [
            'total_campaigns'   => $totalCampaigns,
            'active_campaigns'  => $activeCampaigns,
            'total_budget'      => $totalBudget,
            'total_spend'       => $totalSpend,
            'total_impressions' => $totalImpressions,
            'total_clicks'      => $totalClicks,
            'total_leads'       => $totalLeads,
            'ctr'               => $ctr,
            'conversion_rate'   => $conversionRate,
            'cpc'               => $cpc,
            'cpa'               => $cpa,
        ];

        $trend = [
            'labels' => $trendLabels,
            'spend'  => $trendSpend,
        ];

        return view('client.dashboard', compact('kpis', 'trend'));
    }
}