<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Retailer;
use App\Models\Wallet;
use App\Models\Verification;
use App\Models\KycService;
use Carbon\Carbon;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Arvind Mehta',
            'email' => 'admin@verifyhub.demo',
            'password' => bcrypt('Admin@123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        KycService::create([
            'id' => 'aadhaar',
            'name' => 'Aadhaar Verification',
            'short' => 'Aadhaar',
            'desc' => 'OTP-based Aadhaar',
            'icon' => 'Fingerprint',
            'provider_cost' => 8.50,
            'price' => 20.00,
            'status' => 'active',
        ]);

        $retailer = Retailer::create([
            'id' => 'RT1001',
            'shop' => 'Test Retailer Point',
            'owner' => 'Owner Name',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'mobile' => '9876543210',
            'email' => 'rt1001@example.com',
            'password' => bcrypt('password123'),
            'gst' => '07AAAAA0000A1Z5',
            'pan' => 'ABCDE1234F',
            'status' => 'active',
        ]);

        Wallet::create([
            'retailer_id' => 'RT1001',
            'balance' => 2500.00,
            'currency' => 'INR',
        ]);

        Verification::create([
            'id' => 'VRFTEST001',
            'retailer_id' => 'RT1001',
            'type' => 'aadhaar',
            'doc' => 'XXXX XXXX 1234',
            'name' => 'Sample User',
            'status' => 'verified',
            'amount' => 20.00,
            'response' => 'Success',
            'channel' => 'Web',
            'created_at' => Carbon::now(),
        ]);
    }

    public function test_admin_can_fetch_reports_analytics(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/reports?start_date=' . Carbon::now()->subDays(7)->format('Y-m-d') . '&end_date=' . Carbon::now()->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'generated_at',
                    'date_range',
                    'kpis' => [
                        'verifications',
                        'success_rate',
                        'failed',
                        'billed',
                    ],
                    'daily' => ['chart', 'table'],
                    'monthly' => ['chart', 'table'],
                    'retailer' => ['chart', 'table'],
                    'service' => ['chart', 'table'],
                    'revenue' => ['chart', 'table'],
                    'wallet' => ['table'],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_reports(): void
    {
        $response = $this->getJson('/api/admin/reports');
        $response->assertStatus(401);
    }
}

