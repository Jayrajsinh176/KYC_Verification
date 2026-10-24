<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Notification;
use App\Models\Retailer;
use App\Models\FundRequest;

class NotificationTest extends TestCase
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

        $retailer = Retailer::create([
            'id' => 'RT1001',
            'shop' => 'Test Mobile Point',
            'owner' => 'Test Owner',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'mobile' => '9876543210',
            'email' => 'test@retailer.demo',
            'password' => bcrypt('password123'),
            'gst' => '27AAAAA0000A1Z5',
            'pan' => 'ABCDE1234F',
            'status' => 'active',
        ]);

        FundRequest::create([
            'id' => 'FRQ261023001',
            'retailer_id' => 'RT1001',
            'amount' => 5000.00,
            'mode' => 'UPI',
            'utr' => 'UTR1234567890',
            'remarks' => 'Topup request',
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_retrieve_notifications(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'notifications',
                    'unread_count',
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('data.unread_count'));
    }

    public function test_admin_can_mark_notification_as_read(): void
    {
        $notif = Notification::create([
            'id' => 'NTF_TEST_01',
            'audience' => 'admin',
            'type' => 'info',
            'title' => 'Test Notification',
            'text' => 'This is a test notification message.',
            'read' => false,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/notifications/{$notif->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $notif->refresh();
        $this->assertTrue($notif->read);
    }

    public function test_admin_can_mark_all_notifications_as_read(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/notifications/mark-all-read');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_retailer_can_retrieve_notifications(): void
    {
        $retailer = Retailer::find('RT1001');

        $response = $this->actingAs($retailer, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'notifications',
                    'unread_count',
                ],
            ]);

        $this->assertGreaterThan(0, count($response->json('data.notifications')));
    }

    public function test_retailer_can_mark_all_notifications_as_read(): void
    {
        $retailer = Retailer::find('RT1001');

        $response = $this->actingAs($retailer, 'sanctum')
            ->postJson('/api/notifications/mark-all-read');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $unreadCount = Notification::where('audience', 'RT1001')->where('read', false)->count();
        $this->assertEquals(0, $unreadCount);
    }
}

