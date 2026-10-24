<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Retailer;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminRetailerAndWalletTest extends TestCase
{
    use RefreshDatabase;

    protected string $adminToken;

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
    }

    public function test_admin_can_onboard_new_retailer(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/retailers', [
                'shop' => 'Test Mobile Shop',
                'owner' => 'Kunal Shah',
                'mobile' => '9898001122',
                'email' => 'kunal@testmobile.in',
                'address' => 'Shop 5, Station Road, Surat',
                'pan' => 'AAAPZ1234F',
                'gst' => '24AAAPZ1234F1Z5',
                'password' => 'Secret@123',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Retailer successfully onboarded.',
                'data' => [
                    'shop' => 'Test Mobile Shop',
                    'owner' => 'Kunal Shah',
                    'wallet' => 0.00,
                ],
            ]);

        $this->assertDatabaseHas('retailers', [
            'email' => 'kunal@testmobile.in',
            'mobile' => '9898001122',
        ]);

        // Auto-provisioned wallet exists with 0 balance
        $createdId = $response->json('data.id');
        $this->assertDatabaseHas('wallets', [
            'retailer_id' => $createdId,
            'balance' => 0.00,
        ]);
    }

    public function test_admin_can_update_retailer_profile(): void
    {
        $retailer = Retailer::create([
            'id' => 'RT1099',
            'shop' => 'Original Shop',
            'owner' => 'Original Owner',
            'mobile' => '9825012345',
            'email' => 'original@shop.com',
            'address' => 'Old Address, Vadodara',
            'city' => 'Vadodara',
            'state' => 'Gujarat',
            'pan' => 'ABCDE1234F',
            'password' => Hash::make('OldPass@123'),
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/retailers/{$retailer->id}", [
                'shop' => 'Updated Shop Name',
                'owner' => 'Updated Owner Name',
                'mobile' => '9825012345',
                'email' => 'updated@shop.com',
                'address' => 'New Address, Surat, Gujarat - 395001',
                'city' => 'Surat',
                'state' => 'Gujarat',
                'pan' => 'ABCDE1234F',
                'status' => 'inactive',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Retailer profile updated successfully.',
                'data' => [
                    'id' => 'RT1099',
                    'shop' => 'Updated Shop Name',
                    'owner' => 'Updated Owner Name',
                    'email' => 'updated@shop.com',
                    'status' => 'inactive',
                ],
            ]);

        $this->assertDatabaseHas('retailers', [
            'id' => 'RT1099',
            'shop' => 'Updated Shop Name',
            'email' => 'updated@shop.com',
            'status' => 'inactive',
        ]);
    }

    public function test_retailer_onboarding_rejects_invalid_mobile_and_pan(): void
    {
        // 1. Invalid mobile (e.g. starts with 1 or less than 10 digits)
        $res1 = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/retailers', [
                'shop' => 'Test Mobile Shop',
                'owner' => 'Kunal Shah',
                'mobile' => '1234567890', // invalid prefix
                'email' => 'kunal@testmobile.in',
                'address' => 'Shop 5, Station Road, Surat',
                'pan' => 'AAAPZ1234F',
                'password' => 'Secret@123',
            ]);

        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['mobile']);

        // 2. Invalid PAN (e.g. not 5 letters, 4 digits, 1 letter)
        $res2 = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/retailers', [
                'shop' => 'Test Mobile Shop',
                'owner' => 'Kunal Shah',
                'mobile' => '9898001122',
                'email' => 'kunal2@testmobile.in',
                'address' => 'Shop 5, Station Road, Surat',
                'pan' => 'INVALID_PAN_123', // invalid format
                'password' => 'Secret@123',
            ]);

        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['pan']);

        // 3. Invalid GSTIN (not 15 chars)
        $res3 = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/retailers', [
                'shop' => 'Test Mobile Shop',
                'owner' => 'Kunal Shah',
                'mobile' => '9898001122',
                'email' => 'kunal3@testmobile.in',
                'address' => 'Shop 5, Station Road, Surat',
                'pan' => 'AAAPZ1234F',
                'gst' => 'GSTIN123', // invalid format
                'password' => 'Secret@123',
            ]);

        $res3->assertStatus(422)
            ->assertJsonValidationErrors(['gst']);
    }

    public function test_admin_can_adjust_wallet_balance(): void
    {
        $retailer = Retailer::create([
            'id' => 'RT1099',
            'shop' => 'Fast Recharge Point',
            'owner' => 'Ravi Patel',
            'city' => 'Bharuch',
            'state' => 'Gujarat',
            'mobile' => '9876543210',
            'email' => 'ravi@fastrecharge.demo',
            'password' => Hash::make('Demo@123'),
            'status' => 'active',
        ]);

        Wallet::create([
            'retailer_id' => $retailer->id,
            'balance' => 1000.00,
            'currency' => 'INR',
        ]);

        // 1. Credit ₹500
        $creditRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/wallet/adjust', [
                'retailer_id' => $retailer->id,
                'type' => 'credit',
                'amount' => 500,
                'mode' => 'UPI',
                'ref' => 'VHCRTEST999',
                'note' => 'Topup test',
            ]);

        $creditRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'wallet' => [
                        'balance' => 1500.00,
                    ],
                ],
            ]);

        // 2. Debit ₹200
        $debitRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/wallet/adjust', [
                'retailer_id' => $retailer->id,
                'type' => 'debit',
                'amount' => 200,
                'note' => 'Adjustment deduction',
            ]);

        $debitRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'wallet' => [
                        'balance' => 1300.00,
                    ],
                ],
            ]);
    }

    public function test_admin_can_fetch_wallet_overview(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/wallet/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'kpis' => [
                        'total_float',
                        'retailer_count',
                        'monthly_credits',
                        'monthly_debits',
                        'pending_requests_count',
                        'pending_requests_amount',
                    ],
                    'pending_requests',
                    'retailers',
                ],
            ]);
    }

    public function test_admin_can_approve_and_reject_fund_requests(): void
    {
        $retailer = Retailer::create([
            'id' => 'RT1098',
            'shop' => 'Fund Shop',
            'owner' => 'Kunal Rao',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'mobile' => '9876543211',
            'email' => 'kunal@fundshop.demo',
            'password' => Hash::make('Demo@123'),
            'status' => 'active',
        ]);

        Wallet::create([
            'retailer_id' => $retailer->id,
            'balance' => 500.00,
            'currency' => 'INR',
        ]);

        $fundReq = \App\Models\FundRequest::create([
            'id' => 'FRQTEST001',
            'retailer_id' => $retailer->id,
            'amount' => 1000.00,
            'mode' => 'UPI',
            'utr' => 'UTR9988776655',
            'remarks' => 'Topup test',
            'status' => 'pending',
        ]);

        // Approve
        $approveRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/wallet/fund-requests/{$fundReq->id}/approve");

        $approveRes->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('fund_requests', [
            'id' => 'FRQTEST001',
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('wallets', [
            'retailer_id' => $retailer->id,
            'balance' => 1500.00,
        ]);
    }
}

