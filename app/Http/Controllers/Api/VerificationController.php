<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Verification;
use App\Models\Retailer;
use App\Services\KycVerificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Exception;

class VerificationController extends Controller
{
    use ApiResponse;

    protected KycVerificationService $kycService;

    public function __construct(KycVerificationService $kycService)
    {
        $this->kycService = $kycService;
    }

    /**
     * Perform live/sandbox KYC verification for a retailer.
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'service_type' => 'required|in:aadhaar,pan,bank,esign',
            'payload' => 'required|array',
        ]);

        try {
            $record = $this->kycService->verify(
                retailerId: $validated['retailer_id'],
                serviceType: $validated['service_type'],
                payload: $validated['payload']
            );

            return $this->successResponse($record, 'Verification processed successfully.', 200);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Get paginated and filtered verification records & audit trail.
     */
    public function records(Request $request)
    {
        $baseQuery = Verification::with('retailer');

        // 1. Retailer Scoping (if authenticated retailer, specified retailer_id, or accessing retailer portal)
        $user = $request->user();
        $retailer = null;

        if ($user instanceof Retailer) {
            $retailer = $user;
        } elseif ($request->filled('retailer_id') && $request->retailer_id !== 'all') {
            $retailer = Retailer::find($request->retailer_id);
        } elseif ($request->is('api/retailer/*') || $request->is('retailer/*')) {
            $retailer = Retailer::where('status', 'active')->first() ?? Retailer::first();
        }

        if ($retailer) {
            $baseQuery->where('retailer_id', $retailer->id);
        }

        // 2. Search Query: Request ID, Masked Doc, Customer Name, or Retailer Shop/ID
        if ($request->filled('search')) {
            $search = trim($request->search);
            $baseQuery->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('doc', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhereHas('retailer', function ($rq) use ($search) {
                      $rq->where('shop', 'like', "%{$search}%")
                         ->orWhere('id', 'like', "%{$search}%");
                  });
            });
        }

        // 3. Service Type Filter
        if ($request->filled('type') && $request->type !== 'all') {
            $baseQuery->where('type', strtolower($request->type));
        }

        // 4. Date Range Filters
        if ($request->filled('start_date')) {
            $baseQuery->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $baseQuery->whereDate('created_at', '<=', $request->end_date);
        }

        // Compute summary metrics on the filtered scope (before status filtering)
        $totalCount = (clone $baseQuery)->count();
        $verifiedCount = (clone $baseQuery)->where('status', 'verified')->count();
        $failedCount = (clone $baseQuery)->where('status', 'failed')->count();
        $totalDeducted = (float) (clone $baseQuery)->sum('amount');

        // 5. Status Filter (Applied to records query)
        $query = clone $baseQuery;
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', strtolower($request->status));
        }

        $perPage = (int) $request->input('per_page', 12);
        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $responseData = $paginator->toArray();
        $responseData['stats'] = [
            'total' => $totalCount,
            'verified' => $verifiedCount,
            'failed' => $failedCount,
            'deducted' => $totalDeducted,
        ];

        if ($retailer) {
            $responseData['retailer'] = [
                'id' => $retailer->id,
                'shop' => $retailer->shop,
                'owner' => $retailer->owner,
                'mobile' => $retailer->mobile,
                'email' => $retailer->email,
            ];
        }

        return $this->successResponse($responseData, 'Verification records retrieved successfully.');
    }
}
