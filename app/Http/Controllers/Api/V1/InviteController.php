<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Public invitation acceptance (F10, audit 2026-09-22): open → set password
 * → login. Tokens are stored hashed, expire, and are consumed exactly once.
 */
class InviteController extends Controller
{
    /** Validate a token and reveal only what the accept form needs. */
    public function show(string $token): JsonResponse
    {
        $user = $this->pending($token);
        if (!$user) {
            return response()->json(['message' => 'This invitation is invalid or has expired.'], 410);
        }

        return response()->json(['data' => [
            'name' => $user->name,
            'email' => self::maskEmail($user->email),
            'expires_at' => $user->invitation_expires_at?->toISOString(),
        ]]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $accepted = DB::transaction(function () use ($request, $token) {
            $user = $this->pending($token, lock: true);
            if (!$user) {
                return null;
            }
            $user->forceFill([
                'password' => Hash::make($request->input('password')),
                'invitation_token' => null,
                'invitation_expires_at' => null,
            ])->save();

            return $user;
        });

        if (!$accepted) {
            return response()->json(['message' => 'This invitation is invalid, already used or has expired.'], 410);
        }

        return response()->json(['message' => 'Invitation accepted. You can now sign in.', 'data' => ['email' => $accepted->email]]);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function pending(string $token, bool $lock = false): ?User
    {
        if (!preg_match('/^[A-Za-z0-9]{64}$/', $token)) {
            return null;
        }
        $q = User::where('invitation_token', self::hashToken($token));
        if ($lock) {
            $q->lockForUpdate();
        }
        $user = $q->first();
        if (!$user || !$user->invitation_expires_at || $user->invitation_expires_at->isPast()) {
            return null;
        }

        return $user;
    }

    private static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 1) . str_repeat('•', max(1, strlen($local) - 1)) . '@' . $domain;
    }
}
