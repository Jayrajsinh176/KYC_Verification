<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Retailer;
use App\Models\Wallet;
use App\Models\Verification;
use App\Models\FundRequest;
use App\Models\KycService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RetailerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        KycService::create([
            'id' => 'aadhaar',
            'name' => 'Aadhaar Verification (OTP)',
            'short' => 'Aadhaar',
            'desc' => 'Test Aadhaar',
            'icon' => 'Fingerprint',
            'provider_cost' => 5.0,
            'price' => 20.0,
            'status' => 'active',
        ]);

        $retailer = Retailer::create([
            'id' => 'RT1001',
            'shop' => 'Shree Ganesh Mobile Point',
            'owner' => 'Rakesh Patel',
            'city' => 'Bharuch',
            'state' => 'Gujarat',
            'mobile' => '9824001234',
            'email' => 'retailer@verifyhub.demo',
            'password' => Hash::make('Demo@123'),
            'address' => 'Station Road',
            'pan' => 'AAAP1234F',
            'status' => 'active',
        ]);

        Wallet::create([
            'retailer_id' => 'RT1001',
            'balance' => 3475.00,
            'currency' => 'INR',
        ]);

        Verification::create([
            'id' => 'VRF261022H1T24',
            'retailer_id' => 'RT1001',
            'type' => 'aadhaar',
            'doc' => 'XXXX XXXX 0321',
            'name' => 'Amit Verma',
            'status' => 'verified',
            'response' => 'SUCCESS',
            'amount' => 20.00,
            'created_at' => now(),
        ]);
    }

    public function test_retailer_can_fetch_overview_data(): void
    {
        $retailer = Retailer::find('RT1001');

        $response = $this->actingAs($retailer, 'sanctum')
            ->getJson('/api/retailer/overview');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'retailer' => [
                        'id' => 'RT1001',
                        'shop' => 'Shree Ganesh Mobile Point',
                    ],
                    'wallet' => [
                        'balance' => 3475.00,
                    ],
                    'kpis' => [
                        'total_verifications' => 1,
                        'successful_verifications' => 1,
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.quick_services'));
        $this->assertNotEmpty($response->json('data.last_7_days'));
        $this->assertNotEmpty($response->json('data.recent_activity'));
    }

    public function test_retailer_can_submit_topup_request(): void
    {
        $retailer = Retailer::find('RT1001');

        $response = $this->actingAs($retailer, 'sanctum')
            ->postJson('/api/retailer/fund-requests', [
                'amount' => 5000,
                'mode' => 'UPI',
                'utr' => 'UTR428193746512',
                'transfer_date' => '2026-10-23',
                'remarks' => 'Monthly counter balance top-up',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Wallet top-up request submitted successfully. It will be credited once verified by Super Admin.',
                'data' => [
                    'fund_request' => [
                        'retailer_id' => 'RT1001',
                        'amount' => 5000,
                        'mode' => 'UPI',
                        'utr' => 'UTR428193746512',
                        'status' => 'pending',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('fund_requests', [
            'retailer_id' => 'RT1001',
            'amount' => 5000,
            'utr' => 'UTR428193746512',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('notifications', [
            'title' => 'New fund request',
            'type' => 'info',
        ]);
    }
}
