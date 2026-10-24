<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Verification;
use App\Models\Retailer;
use App\Models\KycService;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    use ApiResponse;

    /**
     * Generate comprehensive reports and analytics datasets.
     */
    public function index(Request $request)
    {
        // 1. Parse and sanitize Date Range
        $endDateInput = $request->input('end_date');
        $startDateInput = $request->input('start_date');

        try {
            $endDate = $endDateInput ? Carbon::parse($endDateInput)->endOfDay() : Carbon::now()->endOfDay();
        } catch (\Exception $e) {
            $endDate = Carbon::now()->endOfDay();
        }

        try {
            $startDate = $startDateInput ? Carbon::parse($startDateInput)->startOfDay() : $endDate->copy()->subDays(29)->startOfDay();
        } catch (\Exception $e) {
            $startDate = $endDate->copy()->subDays(29)->startOfDay();
        }

        // Ensure start is before end
        if ($startDate->gt($endDate)) {
            $temp = $startDate;
            $startDate = $endDate->copy()->subDays(29)->startOfDay();
            $endDate = $temp;
        }

        // 2. Load Services Provider Cost Map
        $services = KycService::all()->keyBy('id');
        $providerCosts = [
            'aadhaar' => (float) ($services['aadhaar']->provider_cost ?? 8.50),
            'pan' => (float) ($services['pan']->provider_cost ?? 3.50),
            'bank' => (float) ($services['bank']->provider_cost ?? 6.00),
            'esign' => (float) ($services['esign']->provider_cost ?? 15.00),
        ];

        // 3. Base Query for Verifications in Range
        $rangeQuery = Verification::whereBetween('created_at', [$startDate, $endDate]);

        $totalVerifications = (clone $rangeQuery)->count();
        $verifiedCount = (clone $rangeQuery)->where('status', 'verified')->count();
        $failedCount = (clone $rangeQuery)->where('status', 'failed')->count();
        $pendingCount = (clone $rangeQuery)->where('status', 'pending')->count();
        $billedAmount = (float) (clone $rangeQuery)->sum('amount');
        $successRate = $totalVerifications > 0 ? round(($verifiedCount / $totalVerifications) * 100, 1) : 0.0;

        // Active retailers in range
        $activeRetailersInRange = (clone $rangeQuery)->distinct('retailer_id')->count('retailer_id');
        $avgPerRetailer = $activeRetailersInRange > 0 ? (int) round($totalVerifications / $activeRetailersInRange) : 0;

        // Range Provider Cost & Margin
        $rangeProviderCost = 0.0;
        $verifsForCost = (clone $rangeQuery)->select('type', DB::raw('count(*) as count'))->groupBy('type')->get();
        foreach ($verifsForCost as $vc) {
            $unitCost = $providerCosts[$vc->type] ?? 5.00;
            $rangeProviderCost += ($vc->count * $unitCost);
        }
        $rangeProviderCost = round($rangeProviderCost, 2);
        $rangeGrossMargin = round($billedAmount - $rangeProviderCost, 2);
        $rangeMarginPct = $billedAmount > 0 ? round(($rangeGrossMargin / $billedAmount) * 100, 1) : 0.0;

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $ymExpr = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";
        $dateExpr = $isSqlite ? "date(created_at)" : "DATE(created_at)";

        // 4. Tab: Daily Verification Breakdown
        $dailyRecords = (clone $rangeQuery)
            ->select(
                DB::raw("{$dateExpr} as date_key"),
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'verified' then 1 else 0 end) as verified"),
                DB::raw("sum(case when status = 'failed' then 1 else 0 end) as failed"),
                DB::raw("sum(case when status = 'pending' then 1 else 0 end) as pending"),
                DB::raw('COALESCE(sum(amount), 0) as billed')
            )
            ->groupBy('date_key')
            ->get()
            ->keyBy('date_key');

        $dailyChartLabels = [];
        $dailyChartVerified = [];
        $dailyChartFailed = [];
        $dailyChartPending = [];
        $dailyTable = [];

        // Build continuous date range
        $curr = $startDate->copy();
        while ($curr->lte($endDate)) {
            $key = $curr->format('Y-m-d');
            $row = $dailyRecords[$key] ?? null;

            $tot = $row ? (int) $row->total : 0;
            $ver = $row ? (int) $row->verified : 0;
            $fai = $row ? (int) $row->failed : 0;
            $pen = $row ? (int) $row->pending : 0;
            $bil = $row ? (float) $row->billed : 0.0;
            $rate = $tot > 0 ? round(($ver / $tot) * 100, 1) : 0.0;

            $dailyChartLabels[] = $curr->format('d M');
            $dailyChartVerified[] = $ver;
            $dailyChartFailed[] = $fai;
            $dailyChartPending[] = $pen;

            $dailyTable[] = [
                'date' => $curr->format('d M Y'),
                'raw_date' => $key,
                'total' => $tot,
                'verified' => $ver,
                'failed' => $fai,
                'pending' => $pen,
                'success_pct' => $rate,
                'billed' => $bil,
            ];

            $curr->addDay();
        }

        // Order table newest first
        $dailyTable = array_reverse($dailyTable);

        // 5. Tab: Monthly Verification Breakdown
        // Compute monthly data across either the full dataset or the selected window
        $monthlyRaw = Verification::select(
                DB::raw("{$ymExpr} as ym"),
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'verified' then 1 else 0 end) as verified"),
                DB::raw("sum(case when status = 'failed' then 1 else 0 end) as failed"),
                DB::raw("sum(case when status = 'pending' then 1 else 0 end) as pending"),
                DB::raw('COALESCE(sum(amount), 0) as billed')
            )
            ->groupBy('ym')
            ->orderBy('ym', 'asc')
            ->get();

        $monthlyChartLabels = [];
        $monthlyChartVerified = [];
        $monthlyChartFailed = [];
        $monthlyChartPending = [];
        $monthlyTable = [];

        foreach ($monthlyRaw as $mRow) {
            $mCarbon = Carbon::createFromFormat('Y-m', $mRow->ym);
            $label = $mCarbon->format('M y'); // e.g. Oct 26
            $tot = (int) $mRow->total;
            $ver = (int) $mRow->verified;
            $fai = (int) $mRow->failed;
            $pen = (int) $mRow->pending;
            $bil = (float) $mRow->billed;
            $rate = $tot > 0 ? round(($ver / $tot) * 100, 1) : 0.0;

            $monthlyChartLabels[] = $label;
            $monthlyChartVerified[] = $ver;
            $monthlyChartFailed[] = $fai;
            $monthlyChartPending[] = $pen;

            $monthlyTable[] = [
                'month' => $label,
                'ym' => $mRow->ym,
                'total' => $tot,
                'verified' => $ver,
                'failed' => $fai,
                'pending' => $pen,
                'success_pct' => $rate,
                'billed' => $bil,
            ];
        }

        // Monthly table ordered newest first
        $monthlyTable = array_reverse($monthlyTable);

        // Monthly KPIs for all-time / monthly view
        $monthlyAllVerifications = Verification::count();
        $monthlyAllVerified = Verification::where('status', 'verified')->count();
        $monthlyAllFailed = Verification::where('status', 'failed')->count();
        $monthlyAllBilled = (float) Verification::sum('amount');
        $monthlyAllSuccessRate = $monthlyAllVerifications > 0 ? round(($monthlyAllVerified / $monthlyAllVerifications) * 100, 1) : 0.0;

        // 6. Tab: Retailer-wise Report
        $retailersList = Retailer::with('wallet')->get()->keyBy('id');
        $retailerAggregates = (clone $rangeQuery)
            ->select(
                'retailer_id',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'verified' then 1 else 0 end) as verified"),
                DB::raw("sum(case when status = 'failed' then 1 else 0 end) as failed"),
                DB::raw("sum(case when status = 'pending' then 1 else 0 end) as pending"),
                DB::raw('COALESCE(sum(amount), 0) as billed')
            )
            ->groupBy('retailer_id')
            ->orderBy('billed', 'desc')
            ->get();

        $retailerChartLabels = [];
        $retailerChartBilled = [];
        $retailerTable = [];

        foreach ($retailerAggregates as $ra) {
            $ret = $retailersList[$ra->retailer_id] ?? null;
            $shopName = $ret ? $ret->shop : $ra->retailer_id;
            $tot = (int) $ra->total;
            $ver = (int) $ra->verified;
            $fai = (int) $ra->failed;
            $pen = (int) $ra->pending;
            $bil = (float) $ra->billed;
            $rate = $tot > 0 ? round(($ver / $tot) * 100, 1) : 0.0;
            $walletBal = $ret && $ret->wallet ? (float) $ret->wallet->balance : 0.0;

            $retailerChartLabels[] = $shopName;
            $retailerChartBilled[] = $bil;

            $retailerTable[] = [
                'retailer_id' => $ra->retailer_id,
                'shop' => $shopName,
                'total' => $tot,
                'verified' => $ver,
                'failed' => $fai,
                'pending' => $pen,
                'success_pct' => $rate,
                'billed' => $bil,
                'wallet_now' => $walletBal,
            ];
        }

        // 7. Tab: Service-wise Report
        $serviceAggregates = (clone $rangeQuery)
            ->select(
                'type',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'verified' then 1 else 0 end) as verified"),
                DB::raw("sum(case when status = 'failed' then 1 else 0 end) as failed"),
                DB::raw("sum(case when status = 'pending' then 1 else 0 end) as pending"),
                DB::raw('COALESCE(sum(amount), 0) as billed')
            )
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $standardServices = [
            'aadhaar' => ['id' => 'aadhaar', 'name' => 'Aadhaar', 'icon' => 'Fingerprint'],
            'pan' => ['id' => 'pan', 'name' => 'PAN', 'icon' => 'CreditCard'],
            'bank' => ['id' => 'bank', 'name' => 'Bank', 'icon' => 'Building2'],
            'esign' => ['id' => 'esign', 'name' => 'e-Sign', 'icon' => 'FileSignature'],
        ];

        $serviceChartLabels = [];
        $serviceChartVerified = [];
        $serviceChartFailed = [];
        $serviceChartPending = [];
        $serviceTable = [];

        foreach ($standardServices as $sId => $sMeta) {
            $sa = $serviceAggregates[$sId] ?? null;
            $tot = $sa ? (int) $sa->total : 0;
            $ver = $sa ? (int) $sa->verified : 0;
            $fai = $sa ? (int) $sa->failed : 0;
            $pen = $sa ? (int) $sa->pending : 0;
            $bil = $sa ? (float) $sa->billed : 0.0;
            $rate = $tot > 0 ? round(($ver / $tot) * 100, 1) : 0.0;

            $unitCost = $providerCosts[$sId] ?? 5.00;
            $cost = round($tot * $unitCost, 2);
            $margin = round($bil - $cost, 2);

            $serviceChartLabels[] = $sMeta['name'];
            $serviceChartVerified[] = $ver;
            $serviceChartFailed[] = $fai;
            $serviceChartPending[] = $pen;

            $serviceTable[] = [
                'service_id' => $sId,
                'name' => $sMeta['name'],
                'icon' => $sMeta['icon'],
                'total' => $tot,
                'verified' => $ver,
                'failed' => $fai,
                'pending' => $pen,
                'success_pct' => $rate,
                'billed' => $bil,
                'provider_cost' => $cost,
                'gross_margin' => $margin,
            ];
        }

        // 8. Tab: Revenue Report (Monthly financial rollup)
        $revenueChartLabels = [];
        $revenueChartRev = [];
        $revenueChartCost = [];
        $revenueChartMargin = [];
        $revenueTable = [];

        $allMonthlyVerifs = Verification::select('type', DB::raw("{$ymExpr} as ym"), DB::raw('count(*) as cnt'), DB::raw('sum(amount) as amt'))
            ->groupBy('ym', 'type')
            ->orderBy('ym', 'asc')
            ->get()
            ->groupBy('ym');

        $totalAllRev = 0.0;
        $totalAllCost = 0.0;

        foreach ($allMonthlyVerifs as $ym => $typeRows) {
            $mCarbon = Carbon::createFromFormat('Y-m', $ym);
            $label = $mCarbon->format('M y');

            $mVerifs = 0;
            $mRev = 0.0;
            $mCost = 0.0;

            foreach ($typeRows as $tr) {
                $mVerifs += (int) $tr->cnt;
                $mRev += (float) $tr->amt;
                $uCost = $providerCosts[$tr->type] ?? 5.00;
                $mCost += ((int) $tr->cnt * $uCost);
            }

            $mCost = round($mCost, 2);
            $mMargin = round($mRev - $mCost, 2);
            $mMarginPct = $mRev > 0 ? round(($mMargin / $mRev) * 100, 1) : 0.0;

            $totalAllRev += $mRev;
            $totalAllCost += $mCost;

            $revenueChartLabels[] = $label;
            $revenueChartRev[] = $mRev;
            $revenueChartCost[] = $mCost;
            $revenueChartMargin[] = $mMargin;

            $revenueTable[] = [
                'month' => $label,
                'ym' => $ym,
                'verifications' => $mVerifs,
                'revenue' => $mRev,
                'provider_cost' => $mCost,
                'gross_margin' => $mMargin,
                'margin_pct' => $mMarginPct,
            ];
        }

        $revenueTable = array_reverse($revenueTable);
        $totalAllMargin = round($totalAllRev - $totalAllCost, 2);
        $totalAllMarginPct = $totalAllRev > 0 ? round(($totalAllMargin / $totalAllRev) * 100, 1) : 0.0;

        // 9. Tab: Wallet Transactions
        $walletTxnsQuery = WalletTransaction::with('retailer')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc');

        $walletCredits = (clone $walletTxnsQuery)->where('type', 'credit')->sum('amount');
        $walletDebits = (clone $walletTxnsQuery)->where('type', 'debit')->sum('amount');
        $walletTxnCount = (clone $walletTxnsQuery)->count();
        $walletNet = round($walletCredits - $walletDebits, 2);

        $walletTable = (clone $walletTxnsQuery)
            ->limit(100)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'created_at' => $tx->created_at->format('d M Y, h:i A'),
                    'retailer_id' => $tx->retailer_id,
                    'shop' => $tx->retailer->shop ?? $tx->retailer_id,
                    'type' => $tx->type,
                    'amount' => (float) $tx->amount,
                    'balance_after' => (float) $tx->balance_after,
                    'ref' => $tx->ref,
                    'mode' => $tx->mode,
                    'note' => $tx->note,
                    'status' => $tx->status,
                ];
            });

        // 10. Assemble and Return Complete Analytics Response
        return $this->successResponse([
            'generated_at' => Carbon::now()->format('d M Y, h:i a'),
            'date_range' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'start_formatted' => $startDate->format('d-m-Y'),
                'end_formatted' => $endDate->format('d-m-Y'),
                'readable' => $startDate->format('d M Y') . ' – ' . $endDate->format('d M Y'),
            ],
            // Primary KPIs for range
            'kpis' => [
                'verifications' => $totalVerifications,
                'success_rate' => $successRate,
                'failed' => $failedCount,
                'billed' => $billedAmount,
                'active_retailers' => $activeRetailersInRange,
                'avg_per_retailer' => $avgPerRetailer,
                'provider_cost' => $rangeProviderCost,
                'gross_margin' => $rangeGrossMargin,
                'margin_pct' => $rangeMarginPct,
            ],
            // Monthly tab specific KPIs
            'monthly_kpis' => [
                'verifications' => $monthlyAllVerifications,
                'success_rate' => $monthlyAllSuccessRate,
                'failed' => $monthlyAllFailed,
                'billed' => $monthlyAllBilled,
            ],
            // Revenue tab specific KPIs
            'revenue_kpis' => [
                'gross_revenue' => round($totalAllRev, 2),
                'provider_cost' => round($totalAllCost, 2),
                'gross_margin' => $totalAllMargin,
                'margin_pct' => $totalAllMarginPct,
            ],
            // Wallet tab specific KPIs
            'wallet_kpis' => [
                'credits' => round((float) $walletCredits, 2),
                'debits' => round((float) $walletDebits, 2),
                'net_flow' => $walletNet,
                'transaction_count' => $walletTxnCount,
            ],
            // Tab Data Payloads
            'daily' => [
                'chart' => [
                    'labels' => $dailyChartLabels,
                    'verified' => $dailyChartVerified,
                    'failed' => $dailyChartFailed,
                    'pending' => $dailyChartPending,
                ],
                'table' => $dailyTable,
            ],
            'monthly' => [
                'chart' => [
                    'labels' => $monthlyChartLabels,
                    'verified' => $monthlyChartVerified,
                    'failed' => $monthlyChartFailed,
                    'pending' => $monthlyChartPending,
                ],
                'table' => $monthlyTable,
            ],
            'retailer' => [
                'chart' => [
                    'labels' => $retailerChartLabels,
                    'billed' => $retailerChartBilled,
                ],
                'table' => $retailerTable,
            ],
            'service' => [
                'chart' => [
                    'labels' => $serviceChartLabels,
                    'verified' => $serviceChartVerified,
                    'failed' => $serviceChartFailed,
                    'pending' => $serviceChartPending,
                ],
                'table' => $serviceTable,
            ],
            'revenue' => [
                'chart' => [
                    'labels' => $revenueChartLabels,
                    'revenue' => $revenueChartRev,
                    'provider_cost' => $revenueChartCost,
                    'gross_margin' => $revenueChartMargin,
                ],
                'table' => $revenueTable,
            ],
            'wallet' => [
                'table' => $walletTable,
            ],
        ], 'Reports data calculated successfully.');
    }
}
