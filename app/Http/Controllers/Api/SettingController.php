<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\AuditLog;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class SettingController extends Controller
{
    use ApiResponse;

    /**
     * Default Fallback Configurations
     */
    protected function getDefaults(): array
    {
        return [
            'company' => [
                'company_name' => 'VerifyHub Technologies Pvt. Ltd. (Demo)',
                'brand_name' => 'VerifyHub',
                'support_email' => 'support@verifyhub.demo',
                'support_phone' => '+91 22 4000 1234',
                'website' => 'www.verifyhub.demo',
                'gstin' => '27AAGCV1234M1Z2',
                'address' => '7th Floor, Demo Business Park, Andheri East, Mumbai, Maharashtra - 400069',
            ],
            'notifications' => [
                'fund_requests' => true,
                'low_wallet_balance' => true,
                'failure_spikes' => true,
                'retailer_onboarding' => true,
                'daily_digest' => false,
                'sms_alerts' => false,
            ],
            'system' => [
                'low_balance_threshold' => 200,
                'min_topup' => 500,
                'session_timeout' => 30,
                'masking_level' => 'strict',
                'auto_retry' => true,
                'maintenance_mode' => false,
            ],
            'providers' => [
                [
                    'id' => 'uidai_otp',
                    'service' => 'Aadhaar Verification',
                    'provider' => 'UIDAI Authorised AUA Sandbox',
                    'endpoint' => 'https://sandbox.uidai.gov.in/kyc/v2.5/otp',
                    'status' => 'connected',
                    'latency' => '240ms',
                    'timeout_ms' => 5000,
                    'auth_type' => 'Bearer Token',
                ],
                [
                    'id' => 'nsdl_pan',
                    'service' => 'PAN Card Verification',
                    'provider' => 'Income Tax / NSDL Source Direct',
                    'endpoint' => 'https://tin.tin.nsdl.com/pan/verify',
                    'status' => 'connected',
                    'latency' => '180ms',
                    'timeout_ms' => 4000,
                    'auth_type' => 'Client Certificate + API Key',
                ],
                [
                    'id' => 'penny_drop',
                    'service' => 'Bank Penny Drop (IMPS)',
                    'provider' => 'NPCI Member Bank IMPS Switch',
                    'endpoint' => 'https://api.npci.org.in/imps/v1/pennypay',
                    'status' => 'connected',
                    'latency' => '520ms',
                    'timeout_ms' => 8000,
                    'auth_type' => 'mTLS + Signing Key',
                ],
                [
                    'id' => 'esign_esp',
                    'service' => 'e-Sign ESP Gateway',
                    'provider' => 'Licensed e-Sign Service Provider (CCA)',
                    'endpoint' => 'https://esp.verifyhub.demo/esign/v3',
                    'status' => 'connected',
                    'latency' => '310ms',
                    'timeout_ms' => 6000,
                    'auth_type' => 'JWT Bearer',
                ],
            ],
        ];
    }

    /**
     * Retrieve all platform settings and administrator profile.
     */
    public function index(Request $request)
    {
        $defaults = $this->getDefaults();
        $storedSettings = Setting::all()->keyBy('key');

        $company = $storedSettings->has('company')
            ? array_merge($defaults['company'], $storedSettings['company']->value)
            : $defaults['company'];

        $notifications = $storedSettings->has('notifications')
            ? array_merge($defaults['notifications'], $storedSettings['notifications']->value)
            : $defaults['notifications'];

        $system = $storedSettings->has('system')
            ? array_merge($defaults['system'], $storedSettings['system']->value)
            : $defaults['system'];

        $providers = $storedSettings->has('providers')
            ? $storedSettings['providers']->value
            : $defaults['providers'];

        // Get admin user profile details
        $user = $request->user();
        $lastLogin = AuditLog::where('actor', $user->email)
            ->where('action', 'ADMIN_LOGIN')
            ->orderBy('created_at', 'desc')
            ->skip(1)
            ->first();

        $lastLoginFormatted = $lastLogin
            ? $lastLogin->created_at->format('d M Y, h:i a')
            : Carbon::now()->subHours(2)->format('d M Y, h:i a');

        $profile = [
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile ?? '9876500001',
            'designation' => $storedSettings->has('admin_designation')
                ? ($storedSettings['admin_designation']->value['designation'] ?? 'Platform Administrator')
                : 'Platform Administrator',
            'role' => 'Super Admin',
            'last_login' => $lastLoginFormatted,
        ];

        return $this->successResponse([
            'company' => $company,
            'profile' => $profile,
            'notifications' => $notifications,
            'system' => $system,
            'providers' => $providers,
        ], 'Platform settings retrieved successfully.');
    }

    /**
     * Update a specific configuration section (company, notifications, system, providers).
     */
    public function update(Request $request, string $section)
    {
        $allowedSections = ['company', 'notifications', 'system', 'providers'];
        if (!in_array($section, $allowedSections, true)) {
            return $this->errorResponse("Invalid settings section '{$section}'.", 422);
        }

        $data = $request->input('data');
        if (!is_array($data)) {
            return $this->errorResponse('Settings payload must be a valid array / object.', 422);
        }

        Setting::updateOrCreate(
            ['key' => $section],
            ['value' => $data]
        );

        AuditLog::record(
            actor: $request->user()->email,
            action: 'SETTING_UPDATE',
            target: ucfirst($section) . ' Configuration',
            ip: $request->ip()
        );

        return $this->successResponse($data, ucfirst($section) . ' settings saved successfully.');
    }

    /**
     * Update administrator profile details (name, designation, mobile).
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'designation' => 'required|string|max:100',
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ]);

        $user = $request->user();
        $user->name = $validated['name'];
        $user->mobile = $validated['mobile'];
        $user->save();

        Setting::updateOrCreate(
            ['key' => 'admin_designation'],
            ['value' => ['designation' => $validated['designation']]]
        );

        AuditLog::record(
            actor: $user->email,
            action: 'ADMIN_PROFILE_UPDATE',
            target: "Profile: {$user->name}",
            ip: $request->ip()
        );

        return $this->successResponse([
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'designation' => $validated['designation'],
            'role' => 'Super Admin',
        ], 'Administrator profile updated successfully.');
    }

    /**
     * Change Administrator Password.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
            'confirm_password' => 'required|same:new_password',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('The current password provided is incorrect.', 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        AuditLog::record(
            actor: $user->email,
            action: 'ADMIN_PASSWORD_CHANGE',
            target: 'Account Credentials',
            ip: $request->ip()
        );

        return $this->successResponse(null, 'Administrator password updated successfully.');
    }

    /**
     * Retrieve system audit logs for the audit activity tab.
     */
    public function auditLogs(Request $request)
    {
        $query = AuditLog::orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('actor', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('target', 'like', "%{$search}%")
                  ->orWhere('ip', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $logs = $query->paginate($perPage);

        return $this->successResponse($logs, 'Audit logs retrieved successfully.');
    }

    /**
     * Reset / Re-seed Demo Data.
     */
    public function resetDemoData(Request $request)
    {
        try {
            Artisan::call('db:seed', ['--force' => true]);

            AuditLog::record(
                actor: $request->user()->email,
                action: 'DEMO_DATA_RESET',
                target: 'VerifyHub Platform State',
                ip: $request->ip()
            );

            return $this->successResponse(null, 'Demo data re-seeded and restored to default baseline successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reset demo data: ' . $e->getMessage(), 500);
        }
    }
}

