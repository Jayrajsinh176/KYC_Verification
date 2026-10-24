<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\FundRequest;
use App\Models\Wallet;
use App\Models\Retailer;
use App\Models\WalletTransaction;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * Helper to resolve the authenticated user or retailer context.
     */
    private function resolveContext(Request $request): array
    {
        $user = $request->user('sanctum') ?? $request->user();
        $isAdmin = ($user instanceof \App\Models\User && $user->role === 'admin');

        $retailerId = null;
        if (!$isAdmin) {
            if ($user instanceof Retailer) {
                $retailerId = $user->id;
            } elseif ($request->header('X-Retailer-Id')) {
                $retailerId = $request->header('X-Retailer-Id');
            } elseif ($request->filled('retailer_id')) {
                $retailerId = $request->input('retailer_id');
            } elseif ($user && isset($user->id)) {
                $retailerId = $user->id;
            } else {
                $retailerId = 'RT1001';
            }
        }

        return [$user, $isAdmin, $retailerId];
    }

    /**
     * Retrieve notifications list and unread count for the authenticated user or retailer.
     */
    public function index(Request $request)
    {
        [$user, $isAdmin, $retailerId] = $this->resolveContext($request);

        // 1. Admin notifications synchronization
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
        } else {
            // 2. Retailer notifications synchronization
            $retailer = Retailer::find($retailerId);
            if ($retailer) {
                // Account Welcome notification
                Notification::firstOrCreate(
                    ['id' => 'NTF_WELCOME_' . $retailer->id],
                    [
                        'audience' => $retailer->id,
                        'type' => 'success',
                        'title' => 'Welcome to VerifyHub!',
                        'text' => "Your merchant account ({$retailer->shop}) is active. Ready to run Aadhaar, PAN, Bank and e-Sign checks.",
                        'read' => false,
                        'link' => '/retailer/overview',
                        'created_at' => $retailer->created_at ?? Carbon::now()->subDays(1),
                    ]
                );

                // Low wallet balance notification (< 500)
                $wallet = Wallet::where('retailer_id', $retailer->id)->first();
                if ($wallet && (float) $wallet->balance < 500) {
                    $bal = '₹' . number_format((float) $wallet->balance, 0);
                    Notification::firstOrCreate(
                        ['id' => 'NTF_LOW_BAL_' . $retailer->id],
                        [
                            'audience' => $retailer->id,
                            'type' => 'warning',
                            'title' => 'Low wallet balance alert',
                            'text' => "Your wallet balance is {$bal}. Request a top-up to continue verifications without interruption.",
                            'read' => false,
                            'link' => '/retailer/overview',
                            'created_at' => Carbon::now()->subMinutes(15),
                        ]
                    );
                }

                // Sync fund requests for this retailer
                $fundRequests = FundRequest::where('retailer_id', $retailer->id)->orderBy('created_at', 'desc')->limit(5)->get();
                foreach ($fundRequests as $fr) {
                    $amt = '₹' . number_format((float) $fr->amount, 0);
                    if ($fr->status === 'approved') {
                        Notification::firstOrCreate(
                            ['id' => 'NTF_FRQ_APP_' . $fr->id],
                            [
                                'audience' => $retailer->id,
                                'type' => 'success',
                                'title' => 'Top-up request approved',
                                'text' => "Your top-up of {$amt} via {$fr->mode} was approved and credited to your wallet.",
                                'read' => false,
                                'link' => '/retailer/overview',
                                'created_at' => $fr->updated_at ?? Carbon::now(),
                            ]
                        );
                    } elseif ($fr->status === 'rejected') {
                        Notification::firstOrCreate(
                            ['id' => 'NTF_FRQ_REJ_' . $fr->id],
                            [
                                'audience' => $retailer->id,
                                'type' => 'warning',
                                'title' => 'Top-up request rejected',
                                'text' => "Your top-up request #{$fr->id} for {$amt} was rejected by Super Admin.",
                                'read' => false,
                                'link' => '/retailer/overview',
                                'created_at' => $fr->updated_at ?? Carbon::now(),
                            ]
                        );
                    }
                }

                // Sync manual admin adjustments/credits
                $recentTxns = WalletTransaction::where('retailer_id', $retailer->id)
                    ->where(function ($q) {
                        $q->where('note', 'like', '%Admin%')
                          ->orWhere('note', 'like', '%Adjustment%')
                          ->orWhere('note', 'like', '%Approved%');
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();

                foreach ($recentTxns as $tx) {
                    $amt = '₹' . number_format((float) $tx->amount, 0);
                    $isCredit = ($tx->type === 'credit');
                    Notification::firstOrCreate(
                        ['id' => 'NTF_TXN_' . $tx->id],
                        [
                            'audience' => $retailer->id,
                            'type' => $isCredit ? 'success' : 'info',
                            'title' => $isCredit ? 'Wallet float credited' : 'Wallet float debited',
                            'text' => "{$amt} was " . ($isCredit ? 'credited to' : 'debited from') . " your wallet (" . $tx->note . ").",
                            'read' => false,
                            'link' => '/retailer/overview',
                            'created_at' => $tx->created_at ?? Carbon::now(),
                        ]
                    );
                }
            }
        }

        // Fetch notifications for the audience
        $audience = $isAdmin
            ? ['admin', 'all']
            : array_values(array_unique(array_filter([$retailerId, 'retailer', 'all'])));

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
     * Mark all notifications as read for current user or retailer.
     */
    public function markAllAsRead(Request $request)
    {
        [$user, $isAdmin, $retailerId] = $this->resolveContext($request);

        $audience = $isAdmin
            ? ['admin', 'all']
            : array_values(array_unique(array_filter([$retailerId, 'retailer', 'all'])));

        Notification::whereIn('audience', $audience)
            ->where('read', false)
            ->update(['read' => true]);

        return $this->successResponse(null, 'All notifications marked as read.');
    }
}
