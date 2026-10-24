<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Arvind Mehta',
            'email' => 'admin@verifyhub.demo',
            'password' => Hash::make('Admin@123'),
            'role' => 'admin',
            'status' => 'active',
            'mobile' => '9876500001',
        ]);
    }

    public function test_admin_can_retrieve_all_settings(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'company' => ['company_name', 'brand_name', 'support_email', 'support_phone', 'website', 'gstin', 'address'],
                    'profile' => ['name', 'email', 'mobile', 'designation', 'role'],
                    'notifications' => ['fund_requests', 'low_wallet_balance', 'failure_spikes', 'retailer_onboarding'],
                    'system' => ['low_balance_threshold', 'min_topup', 'session_timeout', 'masking_level', 'auto_retry', 'maintenance_mode'],
                    'providers',
                ],
            ]);
    }

    public function test_admin_can_update_settings_section(): void
    {
        $companyData = [
            'company_name' => 'VerifyHub Technologies India Pvt Ltd',
            'brand_name' => 'VerifyHub Pro',
            'support_email' => 'contact@verifyhub.demo',
            'support_phone' => '+91 22 4000 9999',
            'website' => 'https://verifyhub.demo',
            'gstin' => '27AAGCV1234M1Z2',
            'address' => 'Floor 10, BKC, Mumbai',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/company', ['data' => $companyData]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('settings', [
            'key' => 'company',
        ]);
    }

    public function test_admin_can_update_profile(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/profile', [
                'name' => 'Arvind K. Mehta',
                'designation' => 'Chief Technology Officer',
                'mobile' => '9876543210',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Arvind K. Mehta')
            ->assertJsonPath('data.designation', 'Chief Technology Officer');

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'name' => 'Arvind K. Mehta',
            'mobile' => '9876543210',
        ]);
    }

    public function test_admin_can_change_password(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/password', [
                'current_password' => 'Admin@123',
                'new_password' => 'SuperSecret@2026',
                'confirm_password' => 'SuperSecret@2026',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->admin->refresh();
        $this->assertTrue(Hash::check('SuperSecret@2026', $this->admin->password));
    }

    public function test_password_change_fails_with_invalid_current_password(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/password', [
                'current_password' => 'WrongPassword',
                'new_password' => 'SuperSecret@2026',
                'confirm_password' => 'SuperSecret@2026',
            ]);

        $response->assertStatus(422);
    }
}

