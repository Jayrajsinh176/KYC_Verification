<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Retailer;
use App\Models\KycService;
use App\Models\RetailerPricing;
use App\Models\Verification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminServicesAndPricingTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;
    protected Retailer $retailer;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::create([
            'name' => 'Arvind Mehta',
            'email' => 'admin@verifyhub.demo',
            'password' => Hash::make('Admin@123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->adminToken = $admin->createToken('admin-token', ['role:admin'])->plainTextToken;

        // Seed 4 core services
        KycService::create([
            'id' => 'aadhaar',
            'name' => 'Aadhaar Verification (OTP)',
            'short' => 'Aadhaar',
            'desc' => 'OTP-based Aadhaar authentication',
            'icon' => 'Fingerprint',
            'provider_cost' => 8.00,
            'price' => 20.00,
            'status' => 'active',
        ]);

        KycService::create([
            'id' => 'pan',
            'name' => 'PAN Card Verification',
            'short' => 'PAN',
            'desc' => 'Validate PAN against income tax',
            'icon' => 'CreditCard',
            'provider_cost' => 3.50,
            'price' => 10.00,
            'status' => 'active',
        ]);

        KycService::create([
            'id' => 'bank',
            'name' => 'Bank Account Verification',
            'short' => 'Bank',
            'desc' => 'IMPS penny drop verification',
            'icon' => 'Landmark',
            'provider_cost' => 6.00,
            'price' => 15.00,
            'status' => 'active',
        ]);

        KycService::create([
            'id' => 'esign',
            'name' => 'Consent Letter with e-Sign',
            'short' => 'e-Sign',
            'desc' => 'Collects legally valid e-signature',
            'icon' => 'FileSignature',
            'provider_cost' => 15.00,
            'price' => 30.00,
            'status' => 'active',
        ]);

        // Seed sample retailer
        $this->retailer = Retailer::create([
            'id' => 'RT1001',
            'shop' => 'Test Digital Kendra',
            'owner' => 'Kunal Shah',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'mobile' => '9898001122',
            'email' => 'kunal@testkendra.in',
            'password' => Hash::make('Secret@123'),
            'status' => 'active',
        ]);

        // Seed sample verifications
        Verification::create([
            'id' => 'VRF001',
            'retailer_id' => 'RT1001',
            'type' => 'aadhaar',
            'doc' => 'XXXX XXXX 1234',
            'status' => 'verified',
            'amount' => 20.00,
            'response' => '{"status":"success"}',
        ]);
        Verification::create([
            'id' => 'VRF002',
            'retailer_id' => 'RT1001',
            'type' => 'aadhaar',
            'doc' => 'XXXX XXXX 5678',
            'status' => 'failed',
            'amount' => 20.00,
            'response' => '{"status":"failed"}',
        ]);
    }

    public function test_admin_can_fetch_kyc_services_with_analytics(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/services');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'services' => [
                        '*' => [
                            'id',
                            'name',
                            'provider_cost',
                            'price',
                            'status',
                            'total_usage',
                            'successful_usage',
                            'success_rate',
                            'margin',
                        ],
                    ],
                    'summary' => [
                        'total_services',
                        'active_services',
                        'full_kyc_price',
                    ],
                ],
            ]);

        $services = collect($response->json('data.services'));
        $aadhaar = $services->firstWhere('id', 'aadhaar');
        $this->assertEquals(2, $aadhaar['total_usage']);
        $this->assertEquals(1, $aadhaar['successful_usage']);
        $this->assertEquals(50.0, $aadhaar['success_rate']);
    }

    public function test_admin_can_toggle_service_status(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson('/api/admin/services/aadhaar', [
                'status' => 'paused',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => 'aadhaar',
                    'status' => 'paused',
                ],
            ]);

        $this->assertDatabaseHas('kyc_services', [
            'id' => 'aadhaar',
            'status' => 'paused',
        ]);
    }

    public function test_admin_can_fetch_pricing_matrix(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/pricing');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'services',
                    'totals',
                    'retailers',
                    'overrides',
                    'custom_rate_cards',
                ],
            ]);
    }

    public function test_admin_can_save_default_rate_card(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/pricing/defaults', [
                'rates' => [
                    [
                        'id' => 'aadhaar',
                        'provider_cost' => 8.50,
                        'price' => 22.00,
                    ],
                    [
                        'id' => 'pan',
                        'provider_cost' => 4.00,
                        'price' => 12.00,
                    ],
                    [
                        'id' => 'bank',
                        'provider_cost' => 6.50,
                        'price' => 16.00,
                    ],
                    [
                        'id' => 'esign',
                        'provider_cost' => 16.00,
                        'price' => 32.00,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('kyc_services', [
            'id' => 'aadhaar',
            'price' => 22.00,
            'provider_cost' => 8.50,
        ]);
    }

    public function test_admin_can_save_and_reset_retailer_custom_pricing(): void
    {
        // 1. Set override
        $overrideResp = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/pricing/override', [
                'retailer_id' => 'RT1001',
                'prices' => [
                    'aadhaar' => 18.00,
                    'pan' => 9.00,
                ],
            ]);

        $overrideResp->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('retailer_pricing', [
            'retailer_id' => 'RT1001',
            'service_id' => 'aadhaar',
            'price' => 18.00,
        ]);

        // 2. Reset overrides
        $resetResp = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson('/api/admin/pricing/override/RT1001');

        $resetResp->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('retailer_pricing', [
            'retailer_id' => 'RT1001',
        ]);
    }
}

