<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycService;
use App\Models\Retailer;
use App\Models\RetailerPricing;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PricingController extends Controller
{
    use ApiResponse;

    /**
     * Get platform pricing matrix: defaults, 30d usage/revenue stats, retailers, and custom rate cards.
     */
    public function index()
    {
        $services = KycService::all();

        // 30-day usage count per service
        $usage30d = DB::table('verifications')
            ->where('created_at', '>=', now()->subDays(30))
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $servicesData = $services->map(function ($svc) use ($usage30d) {
            $usageCount = (int) ($usage30d->get($svc->id)->count ?? 0);
            $margin = round($svc->price - $svc->provider_cost, 2);
            $marginPct = $svc->price > 0 ? round(($margin / $svc->price) * 100, 1) : 0.0;
            $estMonthlyRevenue = round($usageCount * $svc->price, 2);
            $estMonthlyMargin = round($usageCount * $margin, 2);

            return [
                'id' => $svc->id,
                'name' => $svc->name,
                'short' => $svc->short,
                'icon' => $svc->icon,
                'provider_cost' => (float) $svc->provider_cost,
                'price' => (float) $svc->price,
                'margin' => $margin,
                'margin_pct' => $marginPct,
                'usage_30d' => $usageCount,
                'est_monthly_revenue' => $estMonthlyRevenue,
                'est_monthly_margin' => $estMonthlyMargin,
            ];
        });

        $totals = [
            'total_usage_30d' => $servicesData->sum('usage_30d'),
            'total_est_revenue' => $servicesData->sum('est_monthly_revenue'),
            'total_est_margin' => $servicesData->sum('est_monthly_margin'),
        ];

        // Retailers list for selection dropdown
        $retailers = Retailer::select('id', 'shop', 'owner', 'mobile', 'email', 'status', 'city', 'state')
            ->orderBy('id', 'asc')
            ->get();

        // Overrides map: [ retailer_id => [ service_id => price ] ]
        $overrides = RetailerPricing::all();
        $overridesMap = [];
        foreach ($overrides as $ov) {
            $overridesMap[$ov->retailer_id][$ov->service_id] = (float) $ov->price;
        }

        // Active custom rate cards list
        $customRetailerIds = array_keys($overridesMap);
        $customRetailers = Retailer::whereIn('id', $customRetailerIds)->get();

        $defaultPriceMap = $services->pluck('price', 'id')->toArray();

        $customRateCards = $customRetailers->map(function ($r) use ($overridesMap, $defaultPriceMap) {
            $retailerOverrides = $overridesMap[$r->id] ?? [];

            $rates = [];
            $hasCustom = [];
            foreach (['aadhaar', 'pan', 'bank', 'esign'] as $sId) {
                if (isset($retailerOverrides[$sId])) {
                    $rates[$sId] = (float) $retailerOverrides[$sId];
                    $hasCustom[$sId] = true;
                } else {
                    $rates[$sId] = (float) ($defaultPriceMap[$sId] ?? 0);
                    $hasCustom[$sId] = false;
                }
            }

            return [
                'retailer_id' => $r->id,
                'shop' => $r->shop,
                'owner' => $r->owner,
                'status' => $r->status,
                'rates' => $rates,
                'has_custom' => $hasCustom,
            ];
        });

        return $this->success([
            'services' => $servicesData->values(),
            'totals' => $totals,
            'retailers' => $retailers,
            'overrides' => $overridesMap,
            'custom_rate_cards' => $customRateCards->values(),
        ], 'Pricing matrix and rate cards retrieved successfully.');
    }

    /**
     * Save default provider costs and selling prices across KYC services.
     */
    public function saveDefaults(Request $request)
    {
        $validated = $request->validate([
            'rates' => 'required|array',
            'rates.*.id' => 'required|exists:kyc_services,id',
            'rates.*.provider_cost' => 'required|numeric|min:0',
            'rates.*.price' => 'required|numeric|min:0',
        ]);

        foreach ($validated['rates'] as $rate) {
            KycService::where('id', $rate['id'])->update([
                'provider_cost' => $rate['provider_cost'],
                'price' => $rate['price'],
            ]);
        }

        AuditLog::record(
            'Super Admin',
            'Updated Default Rate Card',
            'Updated provider costs and retail selling prices for base catalog',
            $request->ip()
        );

        return $this->success(null, 'Default rate card saved successfully.');
    }

    /**
     * Save custom per-retailer pricing overrides.
     */
    public function saveRetailerPricing(Request $request)
    {
        $validated = $request->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'prices' => 'required|array',
            'prices.aadhaar' => 'nullable|numeric|min:0',
            'prices.pan' => 'nullable|numeric|min:0',
            'prices.bank' => 'nullable|numeric|min:0',
            'prices.esign' => 'nullable|numeric|min:0',
        ]);

        $retailerId = $validated['retailer_id'];
        $defaultServices = KycService::all()->keyBy('id');

        foreach ($validated['prices'] as $serviceId => $customPrice) {
            $defaultPrice = (float) ($defaultServices[$serviceId]->price ?? 0);

            // If empty, null, or matching the default, remove the custom override
            if ($customPrice === null || $customPrice === '' || (float) $customPrice === $defaultPrice) {
                RetailerPricing::where('retailer_id', $retailerId)
                    ->where('service_id', $serviceId)
                    ->delete();
            } else {
                RetailerPricing::updateOrCreate(
                    [
                        'retailer_id' => $retailerId,
                        'service_id' => $serviceId,
                    ],
                    [
                        'price' => (float) $customPrice,
                    ]
                );
            }
        }

        $retailer = Retailer::find($retailerId);
        AuditLog::record(
            'Super Admin',
            'Updated Custom Pricing Override',
            "Custom rate card updated for {$retailer->shop} ({$retailerId})",
            $request->ip()
        );

        // Notify retailer of custom rate card update
        Notification::create([
            'id' => 'NTF_PRICE_' . $retailerId . '_' . time(),
            'audience' => $retailerId,
            'title' => 'Custom rates updated',
            'text' => 'Super Admin updated your verification pricing rates.',
            'type' => 'info',
            'link' => '/retailer/profile',
            'read' => false,
            'created_at' => Carbon::now(),
        ]);

        return $this->success(null, "Custom rate card saved for {$retailer->shop}.");
    }

    /**
     * Reset all custom overrides for a specific retailer back to default.
     */
    public function reset(string $retailerId, Request $request)
    {
        $retailer = Retailer::findOrFail($retailerId);
        RetailerPricing::where('retailer_id', $retailerId)->delete();

        AuditLog::record(
            'Super Admin',
            'Reset Retailer Pricing to Defaults',
            "All custom overrides cleared for {$retailer->shop} ({$retailerId})",
            $request->ip()
        );

        return $this->success(null, "All price overrides for {$retailer->shop} have been reset to defaults.");
    }
}
