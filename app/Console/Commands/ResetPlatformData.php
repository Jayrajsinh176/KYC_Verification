<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\KycService;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Setting;

class ResetPlatformData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:reset-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge all test retailers, transactions, fund requests, verifications, and mock data while preserving Super Admin and core services.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting platform data reset...');

        // 1. Disable Foreign Key Constraints
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 2. Truncate transactional and retailer-related tables
        $tablesToTruncate = [
            'personal_access_tokens',
            'verifications',
            'wallet_transactions',
            'fund_requests',
            'retailer_pricing',
            'wallets',
            'retailers',
            'notifications',
            'audit_logs',
        ];

        foreach ($tablesToTruncate as $table) {
            DB::table($table)->truncate();
            $this->line("  ✓ Truncated table: {$table}");
        }

        // 3. Remove non-admin users
        DB::table('users')->where('email', '!=', 'admin@verifyhub.demo')->delete();

        // 4. Re-enable Foreign Key Constraints
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 5. Ensure Super Admin account exists and has proper credentials
        $admin = User::updateOrCreate(
            ['email' => 'admin@verifyhub.demo'],
            [
                'name' => 'Arvind Mehta',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );
        $this->info("  ✓ Super Admin verified: {$admin->email} (Admin@123)");

        // 6. Ensure default KYC Services Catalog
        $services = [
            [
                'id' => 'aadhaar',
                'name' => 'Aadhaar Verification (OTP)',
                'short' => 'Aadhaar',
                'desc' => 'OTP-based Aadhaar authentication through a UIDAI-authorised partner. Returns name, gender, year of birth and state.',
                'icon' => 'Fingerprint',
                'provider_cost' => 8.00,
                'price' => 20.00,
                'status' => 'active',
            ],
            [
                'id' => 'pan',
                'name' => 'PAN Card Verification',
                'short' => 'PAN',
                'desc' => 'Validate PAN, holder name, status and Aadhaar seeding against the income-tax source.',
                'icon' => 'CreditCard',
                'provider_cost' => 3.50,
                'price' => 10.00,
                'status' => 'active',
            ],
            [
                'id' => 'bank',
                'name' => 'Bank Account Verification (Penny Drop)',
                'short' => 'Bank',
                'desc' => 'Credits ₹1 to the account via IMPS and returns the beneficiary name registered with the bank.',
                'icon' => 'Landmark',
                'provider_cost' => 6.00,
                'price' => 15.00,
                'status' => 'active',
            ],
            [
                'id' => 'esign',
                'name' => 'Consent Letter with e-Sign',
                'short' => 'e-Sign',
                'desc' => 'Generates the customer consent letter and collects a legally valid Aadhaar OTP e-Signature.',
                'icon' => 'FileSignature',
                'provider_cost' => 15.00,
                'price' => 30.00,
                'status' => 'active',
            ],
        ];

        foreach ($services as $svc) {
            KycService::updateOrCreate(['id' => $svc['id']], $svc);
        }
        $this->info('  ✓ Core KYC services catalog maintained (Aadhaar ₹20, PAN ₹10, Bank ₹15, e-Sign ₹30).');

        // 7. Ensure Default Platform Settings exist if not already configured
        if (!Setting::where('key', 'company')->exists()) {
            Setting::create([
                'key' => 'company',
                'value' => [
                    'name' => 'VerifyHub Technologies Private Limited',
                    'cin' => 'U72900GJ2024PTC148902',
                    'gstin' => '24AAACV9081B1Z3',
                    'support_email' => 'support@verifyhub.demo',
                    'support_phone' => '+91 79 4012 8800',
                    'address' => 'Floor 4, Synergy IT Park, Subhanpura, Vadodara, Gujarat 390023',
                ],
            ]);
        }

        // 8. Initial Audit Trail
        AuditLog::create([
            'id' => 'AUD' . date('ymd') . '00001',
            'actor' => 'System',
            'action' => 'PLATFORM_DATA_RESET',
            'target' => 'Clean Slate Initialized - Ready for Manual Onboarding & Testing',
            'ip' => '127.0.0.1',
            'created_at' => now(),
        ]);

        // 9. Initial Welcome Notification for Admin
        Notification::create([
            'id' => 'NTF' . date('ymd') . '0001',
            'audience' => 'admin',
            'type' => 'info',
            'title' => 'Platform Initialized',
            'text' => 'All test retailers and demo transactions have been purged. You can now onboard your first retailer and begin testing.',
            'read' => false,
            'link' => '/admin/retailers',
            'created_at' => now(),
        ]);

        $this->info('Platform data reset completed successfully. The database is now completely clean!');
        return Command::SUCCESS;
    }
}
