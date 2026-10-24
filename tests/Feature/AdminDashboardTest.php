<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Retailer;
use App\Models\Verification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_admin_can_fetch_overview_kpis(): void
    {
        $loginRes = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@verifyhub.demo',
            'password' => 'Admin@123',
        ]);

        $token = $loginRes->json('data.access_token');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/overview');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'kpis' => [
                        'total_retailers' => [],
                        'active_retailers' => [],
                        'total_verifications' => [],
                    ],
                    'category_distribution' => [],
                    'recent_activity' => [],
                ],
            ]);
    }

    public function test_unauthenticated_request_is_blocked(): void
    {
        $response = $this->getJson('/api/admin/overview');
        $response->assertStatus(401);
    }
}

