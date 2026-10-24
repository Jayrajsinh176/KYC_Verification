<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Valid admin token required.',
            ], 401);
        }

        // Verify that the user model is an Admin User and has active status
        if (!($user instanceof \App\Models\User) || $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Super Administrator privileges required.',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is suspended or inactive. Please contact system administrator.',
            ], 403);
        }

        // Verify Sanctum token capability if bearer token is used
        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            if (!$user->tokenCan('role:admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Token does not grant administrator privileges.',
                ], 403);
            }
        }

        return $next($request);
    }
}

