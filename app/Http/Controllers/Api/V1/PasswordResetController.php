<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Password reset (F09, audit 2026-09-22).
 *
 * The old expiry test `now()->diffInMinutes($created) > 60` used Carbon 3's
 * SIGNED difference: for a past timestamp it is negative, so no token ever
 * expired. The mail was a comment. Now: explicit `created_at <= now - TTL`,
 * the link is really sent (queued notification), a token is consumed once
 * under a row lock (two concurrent attempts → one wins), and the remember
 * token is rotated so old "remember me" cookies stop working.
 */
class PasswordResetController extends Controller
{
    public const TTL_MINUTES = 60;

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->input('email'))->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $user->notify(new PasswordResetLink($token, $user->email));
        }

        // Always the same answer — no email enumeration.
        return response()->json(['message' => 'If an account exists, a reset link has been sent.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = $request->input('email');
        $outcome = DB::transaction(function () use ($request, $email) {
            // Row lock: a concurrent reset with the same token waits here and
            // then finds the row gone.
            $record = DB::table('password_reset_tokens')->where('email', $email)->lockForUpdate()->first();
            if (!$record || !Hash::check($request->input('token'), $record->token)) {
                return 'invalid';
            }
            $createdAt = Carbon::parse($record->created_at);
            if ($createdAt->lte(now()->subMinutes(self::TTL_MINUTES))) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();

                return 'expired';
            }
            $user = User::where('email', $email)->first();
            if (!$user) {
                return 'invalid';
            }
            $user->forceFill([
                'password' => Hash::make($request->input('password')),
            ])->save();
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'remember_token')) {
                $user->forceFill(['remember_token' => Str::random(60)])->save();
            }
            DB::table('password_reset_tokens')->where('email', $email)->delete(); // one-time use

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            return 'ok';
        });

        return match ($outcome) {
            'ok' => response()->json(['message' => 'Password has been reset.']),
            'expired' => response()->json(['message' => 'Reset token has expired.'], 422),
            default => response()->json(['message' => 'Invalid or expired reset token.'], 422),
        };
    }
}
