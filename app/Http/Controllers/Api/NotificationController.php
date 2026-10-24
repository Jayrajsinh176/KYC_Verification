<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\FundRequest;
use App\Models\Wallet;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * Retrieve notifications list and unread count for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = ($user->role === 'admin');

        // Automatically sync pending fund requests into admin notification stream if missing
        if ($isAdmin) {
            $pendingRequests = FundRequest::with('retailer')->where('status', 'pending')->get();
            foreach ($pendingRequests as $fr) {
                $notifId = 'NTF_FRQ_' . $fr->id;
                $shopName = $fr->retailer->shop ?? $fr->retailer_id;
                $formattedAmt = '₹' . number_format((float) $fr->amount, 0);

                Notification::firstOrCreate(
                    ['id' => $notifId],
                    [
                        'audience' => 'admin',
                        'type' => 'info',
                        'title' => 'New fund request',
                        'text' => "{$shopName} requested a {$formattedAmt} wallet top-up.",
                        'read' => false,
                        'link' => '/admin/wallet',
                        'created_at' => $fr->created_at ?? Carbon::now(),
                    ]
                );
            }

            // Sync low balance warning if any retailer wallet is below 1500
            $lowWallets = Wallet::with('retailer')->where('balance', '<', 1500)->limit(1)->get();
            foreach ($lowWallets as $lw) {
                $shop = $lw->retailer->shop ?? $lw->retailer_id;
                $bal = '₹' . number_format((float) $lw->balance, 0);
                Notification::firstOrCreate(
                    ['id' => 'NTF_LOW_' . $lw->retailer_id],
                    [
                        'audience' => 'admin',
                        'type' => 'warning',
                        'title' => 'Low wallet balance alert',
                        'text' => "{$shop} balance dropped to {$bal}. Prompt for top-up.",
                        'read' => false,
                        'link' => '/admin/wallet',
                        'created_at' => Carbon::now()->subHours(2),
                    ]
                );
            }

            // Platform health alert
            Notification::firstOrCreate(
                ['id' => 'NTF_REPORT_GEN'],
                [
                    'audience' => 'admin',
                    'type' => 'success',
                    'title' => 'Monthly report generated',
                    'text' => 'Verification throughput and gross revenue reports are ready to view.',
                    'read' => true,
                    'link' => '/admin/reports',
                    'created_at' => Carbon::now()->subDays(1),
                ]
            );
        }

        // Fetch notifications for the audience
        $audience = $isAdmin ? ['admin', 'all'] : [$user->id, 'all'];

        $notifications = Notification::whereIn('audience', $audience)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        $unreadCount = $notifications->where('read', false)->count();

        return $this->successResponse([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ], 'Notifications retrieved successfully.');
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id)
    {
        $notification = Notification::find($id);
        if (!$notification) {
            return $this->errorResponse('Notification not found.', 404);
        }

        $notification->read = true;
        $notification->save();

        return $this->successResponse($notification, 'Notification marked as read.');
    }

    /**
     * Mark all notifications as read for current user.
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $isAdmin = ($user->role === 'admin');
        $audience = $isAdmin ? ['admin', 'all'] : [$user->id, 'all'];

        Notification::whereIn('audience', $audience)
            ->where('read', false)
            ->update(['read' => true]);

        return $this->successResponse(null, 'All notifications marked as read.');
    }
}

