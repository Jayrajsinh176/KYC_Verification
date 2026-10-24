<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Retailer;
use App\Models\Wallet;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RetailerAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed a sample retailer created by Super Admin
        $retailer = Retailer::create([
            'id' => 'RT1001',
            'shop' => 'Shree Ganesh Mobile Point',
            'owner' => 'Rakesh Patel',
            'city' => 'Bharuch',
            'state' => 'Gujarat',
            'mobile' => '9824001234',
            'email' => 'retailer@verifyhub.demo',
            'password' => Hash::make('Demo@123'),
            'address' => 'Station Road, Bharuch, Gujarat',
            'pan' => 'AAAP1234F',
            'gst' => '24AAAP1234F1Z5',
            'status' => 'active',
        ]);

        Wallet::create([
            'retailer_id' => 'RT1001',
            'balance' => 3495.00,
            'currency' => 'INR',
        ]);

        // Seed an inactive retailer
        Retailer::create([
            'id' => 'RT1002',
            'shop' => 'Inactive Merchant Outlet',
            'owner' => 'Suresh Kumar',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'mobile' => '9824009999',
            'email' => 'inactive@retail.demo',
            'password' => Hash::make('Demo@123'),
            'address' => 'Ring Road, Surat',
            'pan' => 'BBBP1234F',
            'status' => 'inactive',
        ]);
    }

    public function test_retailer_can_login_with_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/retailer/login', [
            'identifier' => 'retailer@verifyhub.demo',
            'password' => 'Demo@123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Retailer authenticated successfully.',
                'data' => [
                    'role' => 'retailer',
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => 'RT1001',
                        'email' => 'retailer@verifyhub.demo',
                        'mobile' => '9824001234',
                        'shop' => 'Shree Ganesh Mobile Point',
                        'wallet' => 3495.00,
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.access_token'));

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'actor' => 'retailer@verifyhub.demo',
            'action' => 'RETAILER_LOGIN',
        ]);
    }

    public function test_retailer_can_login_with_mobile_and_password(): void
    {
        $response = $this->postJson('/api/auth/retailer/login', [
            'identifier' => '9824001234',
            'password' => 'Demo@123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'role' => 'retailer',
                    'user' => [
                        'id' => 'RT1001',
                        'mobile' => '9824001234',
                    ],
                ],
            ]);
    }

    public function test_retailer_cannot_login_with_invalid_password(): void
    {
        $response = $this->postJson('/api/auth/retailer/login', [
            'identifier' => 'retailer@verifyhub.demo',
            'password' => 'IncorrectPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid retailer credentials. Please check mobile/email and password.',
            ]);
    }

    public function test_inactive_retailer_login_is_blocked(): void
    {
        $response = $this->postJson('/api/auth/retailer/login', [
            'identifier' => 'inactive@retail.demo',
            'password' => 'Demo@123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your merchant account has been deactivated. Please contact Super Admin.',
            ]);
    }

    public function test_retailer_can_retrieve_profile_and_logout(): void
    {
        $loginResponse = $this->postJson('/api/auth/retailer/login', [
            'identifier' => 'retailer@verifyhub.demo',
            'password' => 'Demo@123',
        ]);

        $token = $loginResponse->json('data.access_token');

        // Check profile
        $meResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me');

        $meResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => 'RT1001',
                    'role' => 'retailer',
                    'email' => 'retailer@verifyhub.demo',
                    'shop' => 'Shree Ganesh Mobile Point',
                ],
            ]);

        // Logout
        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged out.',
            ]);
    }
}

