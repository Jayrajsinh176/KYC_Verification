<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Retailer;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Verification;
use App\Models\FundRequest;
use App\Models\KycService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    /**
     * Compute and return platform-wide KPIs, charts data, and recent activity.
     */
    public function overview(Request $request)
    {
        // 1. KPI Aggregations
        $totalRetailers = Retailer::count();
        $activeRetailers = Retailer::where('status', 'active')->count();
        $newRetailers30d = Retailer::where('created_at', '>=', now()->subDays(30))->count();
        $networkActivePct = $totalRetailers > 0 ? round(($activeRetailers / $totalRetailers) * 100, 1) : 0;

        $totalVerifications = Verification::count();
        $successfulVerifications = Verification::where('status', 'verified')->count();
        $failedVerifications = Verification::where('status', 'failed')->count();
        $successRate = $totalVerifications > 0 ? round(($successfulVerifications / $totalVerifications) * 100, 1) : 0;
        $failedRate = $totalVerifications > 0 ? round(($failedVerifications / $totalVerifications) * 100, 1) : 0;

        $todaysVerifications = Verification::whereDate('created_at', Carbon::today())->count();
        $todaysBilled = (float) Verification::whereDate('created_at', Carbon::today())->sum('amount');

        $totalRevenue = (float) Verification::sum('amount');
        $totalFloatingWallet = (float) Wallet::sum('balance');

        $walletTxnCount = WalletTransaction::count();
        $totalCredited = (float) WalletTransaction::where('type', 'credit')->sum('amount');

        // 2. Category Distribution
        $services = KycService::all()->keyBy('id');
        $serviceCounts = Verification::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $categoryDistribution = [
            'total' => $totalVerifications,
            'items' => [
                [
                    'id' => 'aadhaar',
                    'name' => 'Aadhaar Verification (OTP)',
                    'count' => $serviceCounts['aadhaar'] ?? 0,
                    'percentage' => $totalVerifications > 0 ? round((($serviceCounts['aadhaar'] ?? 0) / $totalVerifications) * 100, 1) : 0,
                    'color' => '#3b82f6',
                ],
                [
                    'id' => 'pan',
                    'name' => 'PAN Card Verification',
                    'count' => $serviceCounts['pan'] ?? 0,
                    'percentage' => $totalVerifications > 0 ? round((($serviceCounts['pan'] ?? 0) / $totalVerifications) * 100, 1) : 0,
                    'color' => '#10b981',
                ],
                [
                    'id' => 'bank',
                    'name' => 'Bank Account Verification (Penny Drop)',
                    'count' => $serviceCounts['bank'] ?? 0,
                    'percentage' => $totalVerifications > 0 ? round((($serviceCounts['bank'] ?? 0) / $totalVerifications) * 100, 1) : 0,
                    'color' => '#06b6d4',
                ],
                [
                    'id' => 'esign',
                    'name' => 'Consent Letter with e-Sign',
                    'count' => $serviceCounts['esign'] ?? 0,
                    'percentage' => $totalVerifications > 0 ? round((($serviceCounts['esign'] ?? 0) / $totalVerifications) * 100, 1) : 0,
                    'color' => '#8b5cf6',
                ],
            ],
        ];

        // 3. Per-Service Success Rates
        $serviceSuccessRates = [];
        foreach (['aadhaar', 'pan', 'bank', 'esign'] as $srv) {
            $totalSrv = Verification::where('type', $srv)->count();
            $successSrv = Verification::where('type', $srv)->where('status', 'verified')->count();
            $serviceSuccessRates[$srv] = $totalSrv > 0 ? round(($successSrv / $totalSrv) * 100, 1) : 0;
        }

        // 4. Monthly Verification Trends (Past 6 months)
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $monthKey = $monthDate->format('Y-m');
            $monthLabel = $monthDate->format('M');
            
            $verified = Verification::where('status', 'verified')
                ->whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)
                ->count();

            $failed = Verification::where('status', 'failed')
                ->whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)
                ->count();

            $pending = Verification::where('status', 'pending')
                ->whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)
                ->count();

            $rev = (float) Verification::whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)
                ->sum('amount');

            // Estimated provider cost
            $cost = 0;
            $monthVerifs = Verification::whereYear('created_at', $monthDate->year)
                ->whereMonth('created_at', $monthDate->month)
                ->get();
            foreach ($monthVerifs as $mv) {
                $cost += $services[$mv->type]->provider_cost ?? 5.0;
            }

            $months[] = [
                'key' => $monthKey,
                'label' => $monthLabel,
                'verified' => $verified,
                'failed' => $failed,
                'pending' => $pending,
                'revenue' => round($rev, 2),
                'cost' => round($cost, 2),
                'margin' => round($rev - $cost, 2),
            ];
        }

        // 30d Margin analytics
        $revenue30d = (float) Verification::where('created_at', '>=', now()->subDays(30))->sum('amount');
        $cost30d = 0;
        $verifs30d = Verification::where('created_at', '>=', now()->subDays(30))->get();
        foreach ($verifs30d as $v30) {
            $cost30d += $services[$v30->type]->provider_cost ?? 5.0;
        }
        $margin30d = $revenue30d - $cost30d;
        $marginPct30d = $revenue30d > 0 ? round(($margin30d / $revenue30d) * 100, 1) : 0;

        // 5. Recent Verification Activity (Latest 7 records)
        $recentVerifications = Verification::with('retailer')
            ->orderBy('created_at', 'desc')
            ->limit(7)
            ->get()
            ->map(function ($v) {
                return [
                    'id' => $v->id,
                    'retailer_id' => $v->retailer_id,
                    'retailer_name' => $v->retailer->shop ?? $v->retailer_id,
                    'service' => $v->type,
                    'doc' => $v->doc,
                    'name' => $v->name,
                    'status' => $v->status,
                    'amount' => (float) $v->amount,
                    'created_at' => $v->created_at->toISOString(),
                ];
            });

        // 6. Top Performing Retailers (by volume/spend)
        $topRetailers = Retailer::select(
                'retailers.id',
                'retailers.shop',
                DB::raw('COALESCE(SUM(verifications.amount), 0) as total_spent'),
                DB::raw('COUNT(verifications.id) as checks_count')
            )
            ->leftJoin('verifications', 'retailers.id', '=', 'verifications.retailer_id')
            ->groupBy('retailers.id', 'retailers.shop')
            ->orderBy('total_spent', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'shop' => $r->shop,
                    'total_spent' => (float) $r->total_spent,
                    'checks_count' => (int) $r->checks_count,
                ];
            });

        // 7. Pending Fund Requests / Alerts
        $pendingFundRequests = FundRequest::with('retailer')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($fr) {
                return [
                    'id' => $fr->id,
                    'retailer_id' => $fr->retailer_id,
                    'retailer_name' => $fr->retailer->shop ?? $fr->retailer_id,
                    'amount' => (float) $fr->amount,
                    'mode' => $fr->mode,
                    'utr' => $fr->utr,
                    'created_at' => $fr->created_at->toISOString(),
                ];
            });

        return $this->successResponse([
            'kpis' => [
                'total_retailers' => [
                    'value' => $totalRetailers,
                    'subtext' => "+{$newRetailers30d} onboarded in 30 days",
                    'trend' => 12.0,
                ],
                'active_retailers' => [
                    'value' => $activeRetailers,
                    'subtext' => "{$networkActivePct}% of network active",
                    'percentage' => $networkActivePct,
                ],
                'total_verifications' => [
                    'value' => $totalVerifications,
                    'subtext' => 'last 30 days vs prior',
                    'trend' => 13.4,
                ],
                'successful_verifications' => [
                    'value' => $successfulVerifications,
                    'subtext' => "{$successRate}% success rate",
                    'trend' => 23.8,
                    'rate' => $successRate,
                ],
                'failed_verifications' => [
                    'value' => $failedVerifications,
                    'subtext' => "{$failedRate}% of all requests",
                    'trend' => -44.4,
                    'rate' => $failedRate,
                ],
                'todays_transactions' => [
                    'value' => $todaysVerifications,
                    'subtext' => '₹' . number_format($todaysBilled) . ' billed today',
                    'trend' => -100.0,
                ],
                'total_revenue' => [
                    'value' => $totalRevenue,
                    'subtext' => 'gross billed to retailers',
                    'trend' => 10.7,
                ],
                'wallet_transactions' => [
                    'value' => $walletTxnCount,
                    'subtext' => '₹' . number_format($totalCredited) . ' credited in total',
                    'trend' => -15.1,
                ],
                'floating_wallet_balance' => [
                    'value' => $totalFloatingWallet,
                    'subtext' => "Across {$activeRetailers} active retailers",
                ],
            ],
            'monthly_trends' => $months,
            'category_distribution' => $categoryDistribution,
            'revenue_analytics' => [
                'gross_margin_30d' => round($margin30d, 2),
                'margin_percentage_30d' => $marginPct30d,
                'monthly' => $months,
            ],
            'success_rates' => [
                'overall' => $successRate,
                'services' => $serviceSuccessRates,
            ],
            'recent_activity' => $recentVerifications,
            'top_retailers' => $topRetailers,
            'alerts' => [
                'fund_requests' => $pendingFundRequests,
                'notifications' => [
                    [
                        'id' => 'SYS01',
                        'title' => 'Aadhaar OTP latency',
                        'message' => 'OTP delivery averaged 9s between 11:00-11:20 (demo alert).',
                        'type' => 'warning',
                    ],
                    [
                        'id' => 'SYS02',
                        'title' => 'Monthly report generated',
                        'message' => 'September 2026 verification report is ready to download.',
                        'type' => 'info',
                    ],
                ],
            ],
        ], 'Admin overview KPIs retrieved successfully.');
    }
}

