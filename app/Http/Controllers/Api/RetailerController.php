<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Retailer;
use App\Models\Wallet;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class RetailerController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $retailers = Retailer::with(['wallet', 'verifications'])
            ->withCount('verifications as api_usage')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'shop' => $r->shop,
                    'owner' => $r->owner,
                    'city' => $r->city,
                    'state' => $r->state,
                    'mobile' => $r->mobile,
                    'email' => $r->email,
                    'address' => $r->address,
                    'pan' => $r->pan,
                    'gst' => $r->gst,
                    'status' => $r->status,
                    'wallet' => $r->wallet ? (float) $r->wallet->balance : 0.00,
                    'api_usage' => (int) $r->api_usage,
                    'total_spent' => (float) $r->verifications->sum('amount'),
                    'createdAt' => $r->created_at->toISOString(),
                ];
            });

        return $this->successResponse($retailers, 'Retailers retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'shop' => 'required|string|max:255',
            'owner' => 'required|string|max:255',
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', 'unique:retailers,mobile'],
            'email' => 'required|email|max:255|unique:retailers,email',
            'address' => 'required|string|min:5|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pan' => ['required', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i'],
            'gst' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i'],
            'password' => 'required|string|min:8',
            'status' => 'nullable|in:active,inactive',
        ], [
            'mobile.regex' => 'The mobile number must be a valid 10-digit Indian phone number starting with 6, 7, 8, or 9.',
            'mobile.unique' => 'A retailer is already registered with this mobile number.',
            'email.unique' => 'A retailer is already registered with this email address.',
            'pan.regex' => 'The PAN number format is invalid. It must be 10 characters (e.g. ABCDE1234F).',
            'gst.regex' => 'The GST number format is invalid. It must be 15 alphanumeric characters (e.g. 24ABCDE1234F1Z5).',
            'password.min' => 'The password must be at least 8 characters long.',
            'address.required' => 'The business address is required.',
        ]);

        $count = Retailer::count();
        $newId = 'RT' . (1001 + $count);
        while (Retailer::where('id', $newId)->exists()) {
            $count++;
            $newId = 'RT' . (1001 + $count);
        }

        $retailer = Retailer::create([
            'id' => $newId,
            'shop' => $validated['shop'],
            'owner' => $validated['owner'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'],
            'address' => $validated['address'],
            'city' => $validated['city'] ?? 'Vadodara',
            'state' => $validated['state'] ?? 'Gujarat',
            'pan' => strtoupper(trim($validated['pan'])),
            'gst' => !empty($validated['gst']) ? strtoupper(trim($validated['gst'])) : null,
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'] ?? 'active',
        ]);

        Wallet::create([
            'retailer_id' => $newId,
            'balance' => 0.00,
            'currency' => 'INR',
        ]);

        AuditLog::record(
            actor: 'Super Admin',
            action: 'ONBOARD_RETAILER',
            target: "{$retailer->shop} ({$retailer->id})",
            ip: $request->ip()
        );

        // Welcome notification for retailer
        Notification::create([
            'id' => 'NTF_WELCOME_' . $newId,
            'audience' => $newId,
            'type' => 'success',
            'title' => 'Welcome to VerifyHub!',
            'text' => "Your merchant account ({$retailer->shop}) is active. Ready to run Aadhaar, PAN, Bank and e-Sign checks.",
            'read' => false,
            'link' => '/retailer/overview',
            'created_at' => Carbon::now(),
        ]);

        return $this->successResponse([
            'id' => $retailer->id,
            'shop' => $retailer->shop,
            'owner' => $retailer->owner,
            'mobile' => $retailer->mobile,
            'email' => $retailer->email,
            'address' => $retailer->address,
            'status' => $retailer->status,
            'wallet' => 0.00,
        ], 'Retailer successfully onboarded.', 201);
    }

    public function update(string $id, Request $request)
    {
        $retailer = Retailer::findOrFail($id);

        $validated = $request->validate([
            'shop' => 'required|string|max:255',
            'owner' => 'required|string|max:255',
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', 'unique:retailers,mobile,' . $id],
            'email' => 'required|email|max:255|unique:retailers,email,' . $id,
            'address' => 'required|string|min:5|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pan' => ['required', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i'],
            'gst' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i'],
            'password' => 'nullable|string|min:8',
            'status' => 'nullable|in:active,inactive',
        ], [
            'mobile.regex' => 'The mobile number must be a valid 10-digit Indian phone number starting with 6, 7, 8, or 9.',
            'mobile.unique' => 'A retailer is already registered with this mobile number.',
            'email.unique' => 'A retailer is already registered with this email address.',
            'pan.regex' => 'The PAN number format is invalid. It must be 10 characters (e.g. ABCDE1234F).',
            'gst.regex' => 'The GST number format is invalid. It must be 15 alphanumeric characters (e.g. 24ABCDE1234F1Z5).',
            'password.min' => 'The password must be at least 8 characters long.',
            'address.required' => 'The business address is required.',
        ]);

        $updateData = [
            'shop' => $validated['shop'],
            'owner' => $validated['owner'],
            'mobile' => $validated['mobile'],
            'email' => $validated['email'],
            'address' => $validated['address'],
            'city' => $validated['city'] ?? $retailer->city,
            'state' => $validated['state'] ?? $retailer->state,
            'pan' => strtoupper(trim($validated['pan'])),
            'gst' => !empty($validated['gst']) ? strtoupper(trim($validated['gst'])) : null,
            'status' => $validated['status'] ?? $retailer->status,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $retailer->update($updateData);

        AuditLog::record(
            actor: 'Super Admin',
            action: 'UPDATE_RETAILER',
            target: "{$retailer->shop} ({$retailer->id})",
            ip: $request->ip()
        );

        return $this->successResponse([
            'id' => $retailer->id,
            'shop' => $retailer->shop,
            'owner' => $retailer->owner,
            'mobile' => $retailer->mobile,
            'email' => $retailer->email,
            'address' => $retailer->address,
            'city' => $retailer->city,
            'state' => $retailer->state,
            'pan' => $retailer->pan,
            'gst' => $retailer->gst,
            'status' => $retailer->status,
            'wallet' => $retailer->wallet ? (float) $retailer->wallet->balance : 0.00,
        ], 'Retailer profile updated successfully.');
    }

    public function toggleStatus(string $id, Request $request)
    {
        $retailer = Retailer::findOrFail($id);
        $newStatus = $retailer->status === 'active' ? 'inactive' : 'active';
        $retailer->status = $newStatus;
        $retailer->save();

        AuditLog::record(
            actor: 'Super Admin',
            action: ($newStatus === 'active' ? 'ACTIVATE' : 'DEACTIVATE') . '_RETAILER',
            target: "{$retailer->shop} ({$retailer->id})",
            ip: $request->ip()
        );

        return $this->successResponse([
            'id' => $retailer->id,
            'status' => $newStatus,
        ], "Retailer status updated to {$newStatus}.");
    }
}

