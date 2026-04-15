<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            RateLimiter::hit($request->throttleKey());

            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        RateLimiter::clear($request->throttleKey());

        $request->session()->regenerate();

        $user = Auth::user();
        $user->last_login_at = now();
        $user->save();

        return response()->json([
            'user' => $user->load('tenant'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('tenant'),
        ]);
    }
}
