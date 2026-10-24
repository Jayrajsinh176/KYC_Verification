<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure admin user exists
        User::updateOrCreate(
            ['email' => 'admin@verifyhub.demo'],
            [
                'name' => 'Arvind Mehta',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@verifyhub.demo',
            'password' => 'Admin@123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Super Admin authenticated successfully.',
                'data' => [
                    'role' => 'admin',
                    'token_type' => 'Bearer',
                    'user' => [
                        'email' => 'admin@verifyhub.demo',
                        'role' => 'admin',
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.access_token'));

        // Verify audit log entry was created
        $this->assertDatabaseHas('audit_logs', [
            'actor' => 'admin@verifyhub.demo',
            'action' => 'ADMIN_LOGIN',
        ]);
    }

    public function test_admin_cannot_login_with_invalid_password(): void
    {
        $response = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@verifyhub.demo',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid administrative credentials.',
            ]);
    }

    public function test_admin_can_retrieve_profile_and_logout(): void
    {
        // 1. Login
        $loginResponse = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@verifyhub.demo',
            'password' => 'Admin@123',
        ]);

        $token = $loginResponse->json('data.access_token');

        // 2. Fetch profile via /me
        $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $meResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'admin@verifyhub.demo',
                    'role' => 'admin',
                ],
            ]);

        // 3. Access protected admin endpoint
        $adminEndpointResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/retailers');

        $adminEndpointResponse->assertStatus(200);

        // 4. Logout
        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged out.',
            ]);

        // Reset in-memory guard cache for next request
        $this->app['auth']->forgetGuards();

        // 5. Subsequent access should fail with 401
        $subsequentResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $subsequentResponse->assertStatus(401);
    }

    public function test_retailer_cannot_access_admin_endpoints(): void
    {
        $retailer = \App\Models\Retailer::create([
            'id' => 'RT9999',
            'shop' => 'Test Outlet',
            'owner' => 'Test Owner',
            'city' => 'Mumbai',
            'state' => 'MH',
            'mobile' => '9999999999',
            'email' => 'test.retailer@example.com',
            'password' => Hash::make('Secret@123'),
            'status' => 'active',
        ]);

        $retailerToken = $retailer->createToken('retailer-token', ['role:retailer'])->plainTextToken;

        // Retailer trying to call Admin endpoint
        $response = $this->withHeader('Authorization', "Bearer {$retailerToken}")
            ->getJson('/api/admin/retailers');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }
}
