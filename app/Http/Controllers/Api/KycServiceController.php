<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycService;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KycServiceController extends Controller
{
    use ApiResponse;

    /**
     * Get all KYC services with live verification analytics and profit margins.
     */
    public function index()
    {
        $services = KycService::all();

        // Aggregate verification stats per service type
        $stats = DB::table('verifications')
            ->select(
                'type',
                DB::raw('count(*) as total_usage'),
                DB::raw("sum(case when status = 'verified' then 1 else 0 end) as successful_usage")
            )
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $enrichedServices = $services->map(function ($svc) use ($stats) {
            $typeStat = $stats->get($svc->id);
            $totalUsage = $typeStat ? (int) $typeStat->total_usage : 0;
            $successfulUsage = $typeStat ? (int) $typeStat->successful_usage : 0;
            $successRate = $totalUsage > 0 ? round(($successfulUsage / $totalUsage) * 100, 1) : 100.0;
            $margin = round($svc->price - $svc->provider_cost, 2);
            $marginPct = $svc->price > 0 ? round(($margin / $svc->price) * 100, 1) : 0.0;

            return [
                'id' => $svc->id,
                'name' => $svc->name,
                'short' => $svc->short,
                'desc' => $svc->desc,
                'icon' => $svc->icon,
                'provider_cost' => (float) $svc->provider_cost,
                'price' => (float) $svc->price,
                'status' => $svc->status,
                'total_usage' => $totalUsage,
                'successful_usage' => $successfulUsage,
                'success_rate' => $successRate,
                'margin' => $margin,
                'margin_pct' => $marginPct,
            ];
        });

        $activeCount = $enrichedServices->where('status', 'active')->count();
        $totalCount = $enrichedServices->count();
        $fullKycPrice = (float) $enrichedServices->where('status', 'active')->sum('price');

        return $this->success([
            'services' => $enrichedServices->values(),
            'summary' => [
                'total_services' => $totalCount,
                'active_services' => $activeCount,
                'full_kyc_price' => $fullKycPrice,
            ],
        ], 'KYC services retrieved successfully.');
    }

    /**
     * Update an individual KYC service status or prices.
     */
    public function update(Request $request, string $id)
    {
        $service = KycService::findOrFail($id);

        $validated = $request->validate([
            'status' => 'nullable|in:active,paused',
            'provider_cost' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
        ]);

        if (isset($validated['status'])) {
            $service->status = $validated['status'];
        }

        if (isset($validated['provider_cost'])) {
            $service->provider_cost = (float) $validated['provider_cost'];
        }

        if (isset($validated['price'])) {
            $service->price = (float) $validated['price'];
        }

        $service->save();

        AuditLog::record(
            'Super Admin',
            'Updated KYC Service Settings',
            "{$service->name} ({$service->id}): status={$service->status}, price=₹{$service->price}",
            $request->ip()
        );

        return $this->success($service, 'Service settings updated successfully.');
    }
}
