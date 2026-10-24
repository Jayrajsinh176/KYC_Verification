<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\FundRequest;
use App\Models\Retailer;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Services\WalletService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class WalletController extends Controller
{
    use ApiResponse;

    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Platform-wide wallet overview: KPIs, pending fund requests, and live retailer balances.
     */
    public function overview(Request $request)
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $totalFloat = (float) Wallet::sum('balance');
        $retailerCount = Retailer::count();

        $monthlyCredits = (float) WalletTransaction::where('type', 'credit')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('amount');
        $monthlyCreditCount = WalletTransaction::where('type', 'credit')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $monthlyDebits = (float) WalletTransaction::where('type', 'debit')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('amount');
        $monthlyDebitCount = WalletTransaction::where('type', 'debit')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $pendingRequests = FundRequest::with('retailer')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingCount = $pendingRequests->count();
        $pendingAmount = (float) $pendingRequests->sum('amount');

        $retailers = Retailer::with('wallet')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'shop' => $r->shop,
                    'owner' => $r->owner,
                    'status' => $r->status,
                    'balance' => (float) ($r->wallet->balance ?? 0.00),
                ];
            })
            ->sortBy('balance')
            ->values();

        return $this->successResponse([
            'kpis' => [
                'total_float' => $totalFloat,
                'retailer_count' => $retailerCount,
                'monthly_credits' => $monthlyCredits,
                'monthly_credit_count' => $monthlyCreditCount,
                'monthly_debits' => $monthlyDebits,
                'monthly_debit_count' => $monthlyDebitCount,
                'pending_requests_count' => $pendingCount,
                'pending_requests_amount' => $pendingAmount,
            ],
            'pending_requests' => $pendingRequests,
            'retailers' => $retailers,
        ], 'Wallet overview retrieved successfully.');
    }

    /**
     * Paginated and filtered wallet ledger transactions.
     */
    public function transactions(Request $request)
    {
        $query = WalletTransaction::with('retailer');

        if ($request->filled('retailer_id') && $request->retailer_id !== 'all') {
            $query->where('retailer_id', $request->retailer_id);
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%")
                  ->orWhereHas('retailer', function ($rq) use ($search) {
                      $rq->where('shop', 'like', "%{$search}%")
                         ->orWhere('id', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $perPage = (int) $request->input('per_page', 10);
        $txns = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->successResponse($txns, 'Wallet transactions retrieved successfully.');
    }

    /**
     * Manual wallet adjustment (Credit / Debit) by Super Admin.
     */
    public function adjust(Request $request)
    {
        $validated = $request->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:1',
            'note' => 'nullable|string',
            'mode' => 'nullable|string',
            'ref' => 'nullable|string',
        ]);

        $note = !empty($validated['note']) ? $validated['note'] : ("Manual Admin " . ucfirst($validated['type']));
        $mode = !empty($validated['mode']) ? $validated['mode'] : ($validated['type'] === 'credit' ? 'NEFT' : 'Manual adjustment');
        $ref = !empty($validated['ref']) ? $validated['ref'] : null;

        try {
            if ($validated['type'] === 'credit') {
                $res = $this->walletService->credit(
                    retailerId: $validated['retailer_id'],
                    amount: (float) $validated['amount'],
                    note: $note,
                    by: 'Super Admin',
                    mode: $mode,
                    ref: $ref
                );
            } else {
                $res = $this->walletService->debit(
                    retailerId: $validated['retailer_id'],
                    amount: (float) $validated['amount'],
                    note: $note,
                    by: 'Super Admin',
                    ref: $ref,
                    mode: $mode
                );
            }

            AuditLog::record(
                actor: 'Super Admin',
                action: "MANUAL_WALLET_" . strtoupper($validated['type']),
                target: "Retailer {$validated['retailer_id']} · ₹{$validated['amount']} ({$mode})",
                ip: $request->ip()
            );

            return $this->successResponse([
                'wallet' => [
                    'retailer_id' => $res['wallet']->retailer_id,
                    'balance' => (float) $res['wallet']->balance,
                ],
                'transaction' => $res['transaction'],
            ], "Wallet successfully {$validated['type']}ed.");
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Fund requests list.
     */
    public function fundRequests(Request $request)
    {
        $query = FundRequest::with('retailer');

        if ($request->has('retailer_id')) {
            $query->where('retailer_id', $request->retailer_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $this->successResponse($query->orderBy('created_at', 'desc')->get());
    }

    /**
     * Retailer submits a top-up fund request with UTR and payment mode.
     */
    public function requestTopup(Request $request)
    {
        $user = $request->user();
        $retailerId = null;

        if ($user instanceof Retailer) {
            $retailerId = $user->id;
        } elseif ($request->filled('retailer_id')) {
            $retailerId = $request->retailer_id;
        } elseif ($user && isset($user->retailer_id)) {
            $retailerId = $user->retailer_id;
        }

        if (!$retailerId) {
            $retailerId = 'RT1001'; // Fallback for session
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:500',
            'mode' => 'required|in:UPI,NEFT,IMPS,RTGS',
            'utr' => 'required|string|min:4|max:64',
            'transfer_date' => 'nullable|string',
            'remarks' => 'nullable|string|max:500',
        ], [
            'amount.min' => 'The minimum top-up amount is ₹500.',
            'amount.required' => 'Please enter a valid top-up amount.',
            'utr.required' => 'UTR or transaction reference number is required.',
        ]);

        $retailer = Retailer::findOrFail($retailerId);

        // Generate unique request ID e.g. FRQ261023A1B2C
        $id = 'FRQ' . Carbon::now()->format('ymd') . strtoupper(Str::random(5));
        while (FundRequest::where('id', $id)->exists()) {
            $id = 'FRQ' . Carbon::now()->format('ymd') . strtoupper(Str::random(5));
        }

        $fundRequest = FundRequest::create([
            'id' => $id,
            'retailer_id' => $retailer->id,
            'amount' => (float) $validated['amount'],
            'mode' => $validated['mode'],
            'utr' => trim($validated['utr']),
            'remarks' => $validated['remarks'] ?? null,
            'status' => 'pending',
        ]);

        // Create Admin Notification for this pending fund request
        Notification::create([
            'id' => 'NTF_FRQ_' . $id,
            'audience' => 'admin',
            'title' => 'New fund request',
            'text' => "{$retailer->shop} requested ₹" . number_format($validated['amount']) . " via {$validated['mode']} (UTR: {$validated['utr']})",
            'type' => 'info',
            'link' => '/admin/wallet',
            'read' => false,
            'created_at' => Carbon::now(),
        ]);

        // Record Audit Log
        AuditLog::record(
            actor: $retailer->shop . ' (' . $retailer->id . ')',
            action: 'SUBMIT_FUND_REQUEST',
            target: "Fund Request #{$id} · ₹{$validated['amount']}",
            ip: $request->ip()
        );

        $pendingSum = (float) FundRequest::where('retailer_id', $retailer->id)
            ->where('status', 'pending')
            ->sum('amount');

        return $this->successResponse([
            'fund_request' => $fundRequest,
            'pending_request_amount' => $pendingSum,
        ], 'Wallet top-up request submitted successfully. It will be credited once verified by Super Admin.');
    }

    /**
     * Approve pending fund top-up request.
     */
    public function approveFundRequest(string $id, Request $request)
    {
        $fundRequest = FundRequest::findOrFail($id);

        if ($fundRequest->status !== 'pending') {
            return $this->errorResponse('This fund request is already ' . $fundRequest->status, 422);
        }

        // Credit wallet atomically
        $this->walletService->credit(
            retailerId: $fundRequest->retailer_id,
            amount: (float) $fundRequest->amount,
            note: "Approved fund request #{$fundRequest->id} ({$fundRequest->mode})",
            by: 'Super Admin',
            mode: $fundRequest->mode,
            ref: $fundRequest->utr ?? $fundRequest->id
        );

        $fundRequest->status = 'approved';
        $fundRequest->save();

        AuditLog::record(
            actor: 'Super Admin',
            action: 'Approved Fund Request',
            target: "{$fundRequest->retailer_id} · ₹{$fundRequest->amount}",
            ip: $request->ip()
        );

        return $this->successResponse($fundRequest, "Fund request #{$id} approved and credited to wallet.");
    }

    /**
     * Reject pending fund top-up request.
     */
    public function rejectFundRequest(string $id, Request $request)
    {
        $fundRequest = FundRequest::findOrFail($id);

        if ($fundRequest->status !== 'pending') {
            return $this->errorResponse('This fund request is already ' . $fundRequest->status, 422);
        }

        $fundRequest->status = 'rejected';
        $fundRequest->save();

        AuditLog::record(
            actor: 'Super Admin',
            action: 'Rejected Fund Request',
            target: "{$fundRequest->retailer_id} · ₹{$fundRequest->amount}",
            ip: $request->ip()
        );

        return $this->successResponse($fundRequest, "Fund request #{$id} was rejected.");
    }
}
