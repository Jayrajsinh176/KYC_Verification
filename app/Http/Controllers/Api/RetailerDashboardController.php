<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Retailer;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Verification;
use App\Models\FundRequest;
use App\Models\KycService;
use App\Models\RetailerPricing;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class RetailerDashboardController extends Controller
{
    use ApiResponse;

    /**
     * Compute and return retailer-specific dashboard KPIs, charts, services, and recent verifications.
     */
    public function overview(Request $request)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? $request->user();

        // Resolve retailer model
        $retailer = null;
        if ($user instanceof Retailer) {
            $retailer = $user;
        } elseif ($request->header('X-Retailer-Id')) {
            $retailer = Retailer::find($request->header('X-Retailer-Id'));
        } elseif ($request->query('retailer_id')) {
            $retailer = Retailer::find($request->query('retailer_id'));
        } elseif ($user && isset($user->retailer_id)) {
            $retailer = Retailer::find($user->retailer_id);
        }

        // Fallback for demo/testing or first active retailer
        if (!$retailer) {
            $retailer = Retailer::where('status', 'active')->first() ?? Retailer::first();
        }

        if (!$retailer) {
            return $this->errorResponse('No retailer found.', 404);
        }

        // 1. Available Wallet Float & Sub-metrics
        $wallet = $retailer->wallet ?? Wallet::where('retailer_id', $retailer->id)->first();
        $walletBalance = $wallet ? (float) $wallet->balance : 0.00;

        $lastCredit = WalletTransaction::where('retailer_id', $retailer->id)
            ->where('type', 'credit')
            ->latest('created_at')
            ->first();
        $lastTopupAmount = $lastCredit ? (float) $lastCredit->amount : 0.00;

        $pendingRequestAmount = (float) FundRequest::where('retailer_id', $retailer->id)
            ->where('status', 'pending')
            ->sum('amount');

        // Dynamic estimated checks left based on wallet balance and average verification cost (~₹19.25)
        $avgCheckCost = 19.25;
        $approxChecksLeft = $walletBalance > 0 ? (int) floor($walletBalance / $avgCheckCost) : 0;

        // 2. Retailer Verification KPIs
        $totalVerifications = Verification::where('retailer_id', $retailer->id)->count();
        $successfulVerifications = Verification::where('retailer_id', $retailer->id)->where('status', 'verified')->count();
        $failedVerifications = Verification::where('retailer_id', $retailer->id)->where('status', 'failed')->count();
        $successRate = $totalVerifications > 0 ? round(($successfulVerifications / $totalVerifications) * 100, 1) : 0;

        // 3. This Month's Expenses & Previous Month Comparison
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        $creditsThisMonth = (float) WalletTransaction::where('retailer_id', $retailer->id)
            ->where('type', 'credit')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $expensesThisMonth = (float) WalletTransaction::where('retailer_id', $retailer->id)
            ->where('type', 'debit')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $expensesLastMonth = (float) WalletTransaction::where('retailer_id', $retailer->id)
            ->where('type', 'debit')
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount');

        $expensesChangePct = 0;
        if ($expensesLastMonth > 0) {
            $expensesChangePct = round((($expensesThisMonth - $expensesLastMonth) / $expensesLastMonth) * 100, 1);
        }

        // 4. Quick Verification Services & Dynamic Custom Pricing
        $defaultServices = KycService::all()->keyBy('id');
        $pricingOverrides = RetailerPricing::where('retailer_id', $retailer->id)->pluck('price', 'service_id')->toArray();

        $servicesConfig = [
            'aadhaar' => [
                'id' => 'aadhaar',
                'name' => 'Aadhaar Verification (OTP)',
                'desc' => 'OTP-based Aadhaar authentication through a UIDAI-authorised partner. Returns name, gender, year of birth and state.',
                'icon' => 'Fingerprint',
                'path' => '/retailer/aadhaar',
                'default_price' => 20.00,
            ],
            'pan' => [
                'id' => 'pan',
                'name' => 'PAN Card Verification',
                'desc' => 'Validate PAN, holder name, status and Aadhaar seeding against the income-tax source.',
                'icon' => 'CreditCard',
                'path' => '/retailer/pan',
                'default_price' => 10.00,
            ],
            'bank' => [
                'id' => 'bank',
                'name' => 'Bank Account Verification (Penny Drop)',
                'desc' => 'Credits ₹1 to the account via IMPS and returns the beneficiary name registered with the bank.',
                'icon' => 'Landmark',
                'path' => '/retailer/bank',
                'default_price' => 15.00,
            ],
            'esign' => [
                'id' => 'esign',
                'name' => 'Consent Letter with e-Sign',
                'desc' => 'Generates the customer consent letter and collects a legally valid Aadhaar OTP e-Signature.',
                'icon' => 'FileSignature',
                'path' => '/retailer/esign',
                'default_price' => 30.00,
            ],
        ];

        $quickServices = [];
        $completeKycPrice = 0;

        foreach ($servicesConfig as $srvId => $cfg) {
            $dbService = $defaultServices->get($srvId);
            $effectivePrice = isset($pricingOverrides[$srvId])
                ? (float) $pricingOverrides[$srvId]
                : ($dbService ? (float) $dbService->price : $cfg['default_price']);
            
            $isAvailable = $dbService ? $dbService->status === 'active' : true;
            $completeKycPrice += $effectivePrice;

            $quickServices[] = [
                'id' => $srvId,
                'name' => $cfg['name'],
                'desc' => $cfg['desc'],
                'icon' => $cfg['icon'],
                'path' => $cfg['path'],
                'price' => $effectivePrice,
                'status' => $isAvailable ? 'available' : 'disabled',
                'has_custom_pricing' => isset($pricingOverrides[$srvId]),
            ];
        }

        // 5. Last 7 Days Daily Verifications by Outcome
        $last7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayDate = Carbon::now()->subDays($i);
            $dateStr = $dayDate->format('Y-m-d');
            $dayLabel = $dayDate->format('D j'); // e.g. "Sat 17", "Fri 23"

            $dayVerified = Verification::where('retailer_id', $retailer->id)
                ->whereDate('created_at', $dateStr)
                ->where('status', 'verified')
                ->count();

            $dayFailed = Verification::where('retailer_id', $retailer->id)
                ->whereDate('created_at', $dateStr)
                ->where('status', 'failed')
                ->count();

            $dayPending = Verification::where('retailer_id', $retailer->id)
                ->whereDate('created_at', $dateStr)
                ->where('status', 'pending')
                ->count();

            $last7Days[] = [
                'date' => $dateStr,
                'day_label' => $dayLabel,
                'verified' => $dayVerified,
                'failed' => $dayFailed,
                'pending' => $dayPending,
                'total' => $dayVerified + $dayFailed + $dayPending,
            ];
        }

        // 6. Recent Activity (Latest 6 verifications)
        $recentVerifications = Verification::where('retailer_id', $retailer->id)
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(function ($v) {
                return [
                    'id' => $v->id,
                    'type' => $v->type,
                    'title' => ucfirst($v->type) . ' - ' . ($v->doc ?? $v->id),
                    'customer_name' => $v->name,
                    'doc' => $v->doc,
                    'status' => $v->status,
                    'amount' => (float) $v->amount,
                    'created_at' => $v->created_at ? $v->created_at->toISOString() : null,
                ];
            });

        return $this->successResponse([
            'retailer' => [
                'id' => $retailer->id,
                'owner' => $retailer->owner,
                'shop' => $retailer->shop,
                'city' => $retailer->city,
                'state' => $retailer->state,
                'mobile' => $retailer->mobile,
                'email' => $retailer->email,
                'status' => $retailer->status,
            ],
            'wallet' => [
                'balance' => $walletBalance,
                'approx_checks_left' => $approxChecksLeft,
                'last_topup' => $lastTopupAmount,
                'pending_request_amount' => $pendingRequestAmount,
                'credits_this_month' => $creditsThisMonth,
                'charges_this_month' => $expensesThisMonth,
            ],
            'kpis' => [
                'total_verifications' => $totalVerifications,
                'successful_verifications' => $successfulVerifications,
                'success_rate' => $successRate,
                'failed_verifications' => $failedVerifications,
                'expenses_this_month' => $expensesThisMonth,
                'expenses_change_pct' => $expensesChangePct,
            ],
            'complete_kyc' => [
                'price' => $completeKycPrice,
                'steps' => ['1. Aadhaar', '2. PAN', '3. Bank', '4. e-Sign'],
                'path' => '/retailer/kyc',
            ],
            'quick_services' => $quickServices,
            'last_7_days' => $last7Days,
            'recent_activity' => $recentVerifications,
        ], 'Retailer overview data retrieved successfully.');
    }

    /**
     * Retrieve complete business profile, active services rate card, and security details.
     */
    public function profile(Request $request)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? $request->user();
        $retailer = null;
        if ($user instanceof Retailer) {
            $retailer = $user;
        } elseif ($request->header('X-Retailer-Id')) {
            $retailer = Retailer::find($request->header('X-Retailer-Id'));
        } elseif ($request->query('retailer_id')) {
            $retailer = Retailer::find($request->query('retailer_id'));
        } elseif ($user && isset($user->retailer_id)) {
            $retailer = Retailer::find($user->retailer_id);
        }

        if (!$retailer) {
            $retailer = Retailer::where('status', 'active')->first() ?? Retailer::first();
        }

        if (!$retailer) {
            return $this->errorResponse('Retailer profile not found.', 404);
        }

        $verificationsCount = Verification::where('retailer_id', $retailer->id)->count();

        // Count per service
        $servicesUsage = [
            'aadhaar' => Verification::where('retailer_id', $retailer->id)->where('type', 'aadhaar')->count(),
            'pan' => Verification::where('retailer_id', $retailer->id)->where('type', 'pan')->count(),
            'bank' => Verification::where('retailer_id', $retailer->id)->where('type', 'bank')->count(),
            'esign' => Verification::where('retailer_id', $retailer->id)->where('type', 'esign')->count(),
        ];

        $servicesConfig = [
            [
                'id' => 'aadhaar',
                'name' => 'Aadhaar verification',
                'icon' => 'Fingerprint',
                'used' => $servicesUsage['aadhaar'],
                'price' => $retailer->getPriceForService('aadhaar'),
                'status' => 'active',
            ],
            [
                'id' => 'pan',
                'name' => 'PAN verification',
                'icon' => 'CreditCard',
                'used' => $servicesUsage['pan'],
                'price' => $retailer->getPriceForService('pan'),
                'status' => 'active',
            ],
            [
                'id' => 'bank',
                'name' => 'Bank verification',
                'icon' => 'Landmark',
                'used' => $servicesUsage['bank'],
                'price' => $retailer->getPriceForService('bank'),
                'status' => 'active',
            ],
            [
                'id' => 'esign',
                'name' => 'e-Sign verification',
                'icon' => 'FileSignature',
                'used' => $servicesUsage['esign'],
                'price' => $retailer->getPriceForService('esign'),
                'status' => 'active',
            ],
        ];

        // Masked PAN
        $maskedPan = $retailer->pan;
        if (strlen($maskedPan) === 10) {
            $maskedPan = substr($maskedPan, 0, 2) . 'XXXXX' . substr($maskedPan, 7);
        }

        // Masked login ID
        $emailParts = explode('@', $retailer->email);
        $maskedEmail = substr($emailParts[0], 0, 2) . '•••@' . ($emailParts[1] ?? 'verifyhub.demo');

        return $this->successResponse([
            'id' => $retailer->id,
            'shop' => $retailer->shop,
            'owner' => $retailer->owner,
            'mobile' => $retailer->mobile,
            'email' => $retailer->email,
            'address' => $retailer->address,
            'pan' => $retailer->pan,
            'masked_pan' => $maskedPan,
            'gst' => $retailer->gst,
            'status' => $retailer->status,
            'partner_since' => $retailer->created_at ? $retailer->created_at->format('d M Y') : 'Recently joined',
            'verifications_count' => $verificationsCount,
            'services' => $servicesConfig,
            'security' => [
                'session_started' => Carbon::now()->subHours(2)->format('d M Y, h:i a'),
                'login_id' => $maskedEmail,
                'two_factor' => false,
            ],
        ], 'Retailer profile retrieved successfully.');
    }

    /**
     * Update retailer business profile details.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $retailer = null;
        if ($user instanceof Retailer) {
            $retailer = $user;
        } elseif ($request->filled('retailer_id')) {
            $retailer = Retailer::find($request->retailer_id);
        }

        if (!$retailer) {
            $retailer = Retailer::where('status', 'active')->first() ?? Retailer::first();
        }

        if (!$retailer) {
            return $this->errorResponse('Retailer not found.', 404);
        }

        $validated = $request->validate([
            'shop' => 'required|string|max:255',
            'owner' => 'required|string|max:255',
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'email' => 'required|email|max:255',
            'address' => 'required|string|min:5|max:500',
            'gst' => 'nullable|string|max:20',
        ]);

        $retailer->shop = $validated['shop'];
        $retailer->owner = $validated['owner'];
        $retailer->mobile = $validated['mobile'];
        $retailer->email = $validated['email'];
        $retailer->address = $validated['address'];
        if (array_key_exists('gst', $validated)) {
            $retailer->gst = $validated['gst'];
        }
        $retailer->save();

        return $this->successResponse($retailer, 'Business profile updated successfully.');
    }

    /**
     * Change password for retailer account.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        $retailer = null;
        if ($user instanceof Retailer) {
            $retailer = $user;
        } elseif ($request->filled('retailer_id')) {
            $retailer = Retailer::find($request->retailer_id);
        }

        if (!$retailer) {
            $retailer = Retailer::where('status', 'active')->first() ?? Retailer::first();
        }

        if (!$retailer) {
            return $this->errorResponse('Retailer not found.', 404);
        }

        $validated = $request->validate([
            'new_password' => 'required|string|min:8|same:confirm_new_password',
            'confirm_new_password' => 'required|string',
        ]);

        $retailer->password = Hash::make($validated['new_password']);
        $retailer->save();

        return $this->successResponse(null, 'Password updated successfully.');
    }

    /**
     * Invalidate all session tokens.
     */
    public function logoutAll(Request $request)
    {
        $user = $request->user();
        if ($user && method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
        return $this->successResponse(null, 'Signed out of all devices successfully.');
    }
}

