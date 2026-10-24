<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Retailer;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Authenticate Super Administrator.
     */
    public function adminLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Invalid administrative credentials.', 401);
        }

        if (!$user->isAdmin()) {
            return $this->errorResponse('Access restricted. User does not have administrative privileges.', 403);
        }

        if (!$user->isActive()) {
            return $this->errorResponse('Your administrative account has been deactivated. Please contact security.', 403);
        }

        // Generate Sanctum Bearer token with 'role:admin' capability
        $token = $user->createToken('admin-access-token', ['role:admin'])->plainTextToken;

        // Record audit trail
        AuditLog::record(
            actor: $user->email,
            action: 'ADMIN_LOGIN',
            target: 'Admin Portal',
            ip: $request->ip()
        );

        return $this->successResponse([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'role' => 'admin',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
            ],
        ], 'Super Admin authenticated successfully.');
    }

    public function retailerLogin(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string', // email or mobile
            'password' => 'required|string',
        ]);

        $identifier = trim($request->identifier);

        $retailer = Retailer::where('email', $identifier)
            ->orWhere('mobile', $identifier)
            ->first();

        if (!$retailer || !Hash::check($request->password, $retailer->password)) {
            return $this->errorResponse('Invalid retailer credentials. Please check mobile/email and password.', 401);
        }

        if ($retailer->status !== 'active') {
            return $this->errorResponse('Your merchant account has been deactivated. Please contact Super Admin.', 403);
        }

        $token = $retailer->createToken('retailer-token', ['role:retailer'])->plainTextToken;

        // Record audit log
        AuditLog::record(
            actor: $retailer->email ?? $retailer->mobile,
            action: 'RETAILER_LOGIN',
            target: 'Retailer Terminal',
            ip: $request->ip()
        );

        return $this->successResponse([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'token' => $token, // for compatibility
            'role' => 'retailer',
            'user' => [
                'id' => $retailer->id,
                'name' => $retailer->owner,
                'shop' => $retailer->shop,
                'email' => $retailer->email,
                'mobile' => $retailer->mobile,
                'city' => $retailer->city,
                'state' => $retailer->state,
                'pan' => $retailer->pan,
                'gst' => $retailer->gst,
                'status' => $retailer->status,
                'wallet' => $retailer->wallet ? (float) $retailer->wallet->balance : 0.00,
            ],
        ], 'Retailer authenticated successfully.');
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $actor = $user->email ?? $user->mobile ?? 'User #' . $user->id;
            
            // Record audit trail
            AuditLog::record(
                actor: $actor,
                action: 'LOGOUT',
                target: 'API Session',
                ip: $request->ip()
            );

            $user->currentAccessToken()->delete();
        }

        return $this->successResponse(null, 'Successfully logged out.');
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if ($user instanceof User) {
            return $this->successResponse([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'mobile' => $user->mobile,
            ], 'Admin profile retrieved successfully.');
        }

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->owner ?? $user->name,
            'shop' => $user->shop ?? null,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'role' => 'retailer',
            'status' => $user->status,
            'wallet' => $user->wallet ? $user->wallet->balance : 0.00,
        ], 'User profile retrieved successfully.');
    }
}

