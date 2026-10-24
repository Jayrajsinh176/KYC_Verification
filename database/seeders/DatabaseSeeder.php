<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Retailer;
use App\Models\KycService;
use App\Models\RetailerPricing;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\FundRequest;
use App\Models\Verification;
use App\Models\AuditLog;
use App\Models\Setting;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Super Admin User
        User::updateOrCreate(
            ['email' => 'admin@verifyhub.demo'],
            [
                'name' => 'Arvind Mehta',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        // 2. Seed KYC Services
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

        // 3. Seed 12 Retailers (10 active, 2 inactive)
        $retailersData = [
            ['Shree Ganesh Mobile Point', 'Rakesh Patel', 'Bharuch', 'Gujarat', '9824001234', 'retailer@verifyhub.demo', 'Demo@123', '24AAAP1234F1Z5', 'AAAP1234F', 'active'],
            ['Sai Digital Services', 'Pooja Sharma', 'Pune', 'Maharashtra', '9827005678', 'pooja.sharma@retail.demo', 'Retail@123', '27AAAP5678F1Z8', 'AAAP5678F', 'active'],
            ['Annapurna Finserv Kendra', 'Vikram Singh', 'Jaipur', 'Rajasthan', '9808009012', 'vikram.singh@retail.demo', 'Retail@123', '08AAAP9012F1Z2', 'AAAP9012F', 'active'],
            ['Krishna CSC Centre', 'Mahesh Yadav', 'Lucknow', 'Uttar Pradesh', '9809003456', 'mahesh.yadav@retail.demo', 'Retail@123', null, 'AAAP3456F', 'inactive'],
            ['Royal Enterprises', 'Imran Shaikh', 'Surat', 'Gujarat', '9824007890', 'imran.shaikh@retail.demo', 'Retail@123', '24AAAP7890F1Z4', 'AAAP7890F', 'active'],
            ['Balaji Online Seva', 'Suresh Reddy', 'Hyderabad', 'Telangana', '9836001122', 'suresh.reddy@retail.demo', 'Retail@123', '36AAAP1122F1Z9', 'AAAP1122F', 'active'],
            ['Metro Fintech Hub', 'Ananya Iyer', 'Chennai', 'Tamil Nadu', '9840003344', 'ananya.iyer@retail.demo', 'Retail@123', '33AAAP3344F1Z1', 'AAAP3344F', 'active'],
            ['Om Sai Multiservices', 'Deepak Kulkarni', 'Nagpur', 'Maharashtra', '9822005566', 'deepak.k@retail.demo', 'Retail@123', null, 'AAAP5566F', 'inactive'],
            ['Laxmi Money Point', 'Harpreet Kaur', 'Ludhiana', 'Punjab', '9815007788', 'harpreet.k@retail.demo', 'Retail@123', '03AAAP7788F1Z3', 'AAAP7788F', 'active'],
            ['Navkar Digital Point', 'Jignesh Shah', 'Ahmedabad', 'Gujarat', '9825009900', 'jignesh.shah@retail.demo', 'Retail@123', '24AAAP9900F1Z7', 'AAAP9900F', 'active'],
            ['Bharat Seva Kendra', 'Arjun Das', 'Kolkata', 'West Bengal', '9830002233', 'arjun.das@retail.demo', 'Retail@123', null, 'AAAP2233F', 'active'],
            ['Green Valley Traders', 'Neha Joshi', 'Indore', 'Madhya Pradesh', '9826004455', 'neha.joshi@retail.demo', 'Retail@123', '23AAAP4455F1Z6', 'AAAP4455F', 'active'],
        ];

        $retailerIds = [];
        foreach ($retailersData as $idx => $r) {
            $id = 'RT' . (1001 + $idx);
            $retailerIds[] = $id;

            $retailer = Retailer::updateOrCreate(
                ['id' => $id],
                [
                    'shop' => $r[0],
                    'owner' => $r[1],
                    'city' => $r[2],
                    'state' => $r[3],
                    'mobile' => $r[4],
                    'email' => $r[5],
                    'password' => Hash::make($r[6]),
                    'gst' => $r[7],
                    'pan' => $r[8],
                    'address' => "Shop " . (10 + $idx) . ", Market Road, {$r[2]}, {$r[3]} - 390001",
                    'status' => $r[9],
                    'created_at' => now()->subDays(rand(5, 60)),
                ]
            );

            // Initial Prepaid Wallet
            $initialBal = ($r[9] === 'active') ? rand(1500, 5000) : 0.00;
            Wallet::updateOrCreate(
                ['retailer_id' => $id],
                [
                    'balance' => $initialBal,
                    'currency' => 'INR',
                ]
            );

            // Opening Balance Ledger
            if ($initialBal > 0) {
                WalletTransaction::updateOrCreate(
                    ['id' => 'TXN' . date('ymd') . rand(100000, 999999)],
                    [
                        'retailer_id' => $id,
                        'type' => 'credit',
                        'amount' => $initialBal,
                        'balance_after' => $initialBal,
                        'ref' => 'VHCR' . rand(10000000, 99999999),
                        'mode' => 'NEFT',
                        'note' => 'Prepaid float deposit',
                        'status' => 'success',
                        'by' => 'Super Admin',
                        'created_at' => now()->subDays(rand(10, 45)),
                    ]
                );
            }
        }

        // 4. Custom Pricing Overrides
        RetailerPricing::updateOrCreate(
            ['retailer_id' => 'RT1003', 'service_id' => 'aadhaar'],
            ['price' => 18.00]
        );
        RetailerPricing::updateOrCreate(
            ['retailer_id' => 'RT1003', 'service_id' => 'pan'],
            ['price' => 9.00]
        );
        RetailerPricing::updateOrCreate(
            ['retailer_id' => 'RT1003', 'service_id' => 'bank'],
            ['price' => 14.00]
        );
        RetailerPricing::updateOrCreate(
            ['retailer_id' => 'RT1007', 'service_id' => 'pan'],
            ['price' => 9.50]
        );

        // 5. Seed Fund Requests
        FundRequest::updateOrCreate(
            ['id' => 'FRQ261022401'],
            [
                'retailer_id' => 'RT1001',
                'amount' => 5000.00,
                'mode' => 'UPI',
                'utr' => 'UTR928374829102',
                'remarks' => 'Prepaid wallet replenishment for counter verifications',
                'status' => 'pending',
                'created_at' => now()->subDay(),
            ]
        );

        FundRequest::updateOrCreate(
            ['id' => 'FRQ261022402'],
            [
                'retailer_id' => 'RT1006',
                'amount' => 2000.00,
                'mode' => 'IMPS',
                'utr' => 'UTR109283746501',
                'remarks' => 'IMPS topup for weekend rush',
                'status' => 'pending',
                'created_at' => now()->subDay(),
            ]
        );

        // 6. Seed 547 Verifications with realistic distribution (~90.9% success)
        // Only seed if not already seeded to prevent bloat
        if (Verification::count() < 100) {
            $customerNames = [
                'Amit Kumar Verma', 'Priya Nair', 'Rohit Agarwal', 'Sneha Kulkarni',
                'Mohammed Arif', 'Kavita Joshi', 'Sanjay Gupta', 'Divya Menon',
                'Rahul Choudhary', 'Meera Pillai', 'Nikhil Bansal', 'Farhan Qureshi',
                'Swati Deshmukh', 'Gaurav Saxena', 'Ritu Malhotra', 'Vivek Tiwari'
            ];

            $types = [
                ['type' => 'aadhaar', 'amount' => 20.00, 'count' => 167],
                ['type' => 'pan', 'amount' => 10.00, 'count' => 171],
                ['type' => 'bank', 'amount' => 15.00, 'count' => 136],
                ['type' => 'esign', 'amount' => 30.00, 'count' => 73],
            ];

            $totalCreated = 0;
            foreach ($types as $tConfig) {
                for ($k = 0; $k < $tConfig['count']; $k++) {
                    $isVerified = ($k % 11 !== 0); // ~91% success rate
                    $status = $isVerified ? 'verified' : 'failed';
                    $daysAgo = rand(1, 150);
                    $cName = $customerNames[array_rand($customerNames)];
                    $rId = $retailerIds[array_rand($retailerIds)];

                    $docMask = match ($tConfig['type']) {
                        'aadhaar' => 'XXXX XXXX ' . rand(1000, 9999),
                        'pan' => strtoupper(substr(md5(rand()), 0, 2)) . 'XXXXX' . rand(10, 99) . 'F',
                        'bank' => 'XXXXXX' . rand(1000, 9999),
                        'esign' => 'CL-26' . rand(1000000, 9999999),
                    };

                    Verification::create([
                        'id' => 'VRF2610' . str_pad($totalCreated + 1, 5, '0', STR_PAD_LEFT),
                        'retailer_id' => $rId,
                        'type' => $tConfig['type'],
                        'doc' => $docMask,
                        'name' => $cName,
                        'status' => $status,
                        'amount' => $tConfig['amount'],
                        'response' => $isVerified ? '200 · VALID · Gateway verified record' : '422 · MISMATCH · Document could not be validated',
                        'details' => ['holder' => $cName, 'source' => 'Sandbox Gateway'],
                        'channel' => 'Web dashboard',
                        'created_at' => Carbon::now()->subDays($daysAgo)->subHours(rand(1, 23)),
                    ]);

                    $totalCreated++;
                }
            }
        }

        // 7. System Audit Log
        AuditLog::create([
            'id' => 'AUD' . date('ymd') . '00001',
            'actor' => 'System',
            'action' => 'DATABASE_MIGRATION_AND_SEED',
            'target' => 'VerifyHub Platform Core',
            'ip' => '127.0.0.1',
            'created_at' => now(),
        ]);
    }
}
