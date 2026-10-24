<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Retailer;
use App\Models\Verification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminVerificationRecordsTest extends TestCase
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

        $this->retailer = Retailer::create([
            'id' => 'RT1001',
            'shop' => 'Shree Ganesh Mobile Point',
            'owner' => 'Rakesh Patel',
            'city' => 'Bharuch',
            'state' => 'Gujarat',
            'mobile' => '9824001234',
            'email' => 'retailer@verifyhub.demo',
            'password' => Hash::make('Demo@123'),
            'status' => 'active',
        ]);

        Verification::create([
            'id' => 'VRF261022H1T24',
            'retailer_id' => 'RT1001',
            'type' => 'aadhaar',
            'doc' => 'XXXX XXXX 1012',
            'name' => 'Amit Kumar Verma',
            'status' => 'verified',
            'amount' => 20.00,
            'response' => '200 · VERIFIED · UIDAI OTP authenticated',
            'details' => ['Gender' => 'Male', 'Year' => '1992'],
            'created_at' => now(),
        ]);

        Verification::create([
            'id' => 'VRF26102205816',
            'retailer_id' => 'RT1001',
            'type' => 'pan',
            'doc' => 'CFXXXXX51Q',
            'name' => 'Failed Customer',
            'status' => 'failed',
            'amount' => 10.00,
            'response' => '404 · INVALID · PAN not found',
            'details' => null,
            'created_at' => now()->subHour(),
        ]);
    }

    public function test_admin_can_fetch_verification_records_paginated(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/records');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'retailer_id',
                            'type',
                            'doc',
                            'name',
                            'status',
                            'amount',
                            'response',
                            'created_at',
                            'retailer',
                        ],
                    ],
                    'current_page',
                    'total',
                ],
            ]);
    }

    public function test_admin_can_filter_records_by_service_and_status(): void
    {
        // 1. Filter Aadhaar
        $aadhaarResp = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/records?type=aadhaar');

        $aadhaarResp->assertStatus(200);
        $this->assertEquals(1, $aadhaarResp->json('data.total'));
        $this->assertEquals('VRF261022H1T24', $aadhaarResp->json('data.data.0.id'));

        // 2. Filter Failed status
        $failedResp = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/records?status=failed');

        $failedResp->assertStatus(200);
        $this->assertEquals(1, $failedResp->json('data.total'));
        $this->assertEquals('VRF26102205816', $failedResp->json('data.data.0.id'));
    }

    public function test_admin_can_search_records(): void
    {
        $searchResp = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/records?search=1012');

        $searchResp->assertStatus(200);
        $this->assertEquals(1, $searchResp->json('data.total'));
        $this->assertEquals('VRF261022H1T24', $searchResp->json('data.data.0.id'));
    }
}

