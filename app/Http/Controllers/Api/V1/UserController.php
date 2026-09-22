<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // F10: the (hashed) invitation token never leaves the server — only the status.
        $users = User::where('tenant_id', $request->user()->tenant_id)
            ->select(['id', 'name', 'email', 'role', 'last_login_at', 'invitation_token', 'invitation_expires_at', 'created_at'])
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                ...collect($u->toArray())->except(['invitation_token'])->all(),
                'status' => $u->invitation_token ? 'pending' : 'active',
                'invitation_expired' => (bool) ($u->invitation_token && $u->invitation_expires_at?->isPast()),
            ]);

        return response()->json(['data' => $users]);
    }

    public function invite(Request $request): JsonResponse
    {
        if (!$request->user()->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:editor,admin,viewer,author'],
        ]);

        // Only the owner may create another admin (parity with updateRole).
        if ($request->input('role') === 'admin' && !$request->user()->isOwner()) {
            return response()->json(['message' => 'Only the owner can invite an admin.'], 403);
        }

        // Check if email already exists in tenant
        $existing = User::where('tenant_id', $request->user()->tenant_id)
            ->where('email', $request->input('email'))
            ->first();

        if ($existing) {
            return response()->json(['message' => 'A user with this email already exists.'], 422);
        }

        $token = Str::random(64);
        $expires = now()->addHours(48);

        $user = User::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make(Str::random(32)), // Placeholder until they set their own
            'role' => $request->input('role'),
            'invitation_token' => InviteController::hashToken($token), // F10: hashed at rest
            'invitation_expires_at' => $expires,
            'invited_by' => $request->user()->id,
        ]);

        // F10: the invitation is really sent; the raw link is returned ONCE to
        // the admin who created it (so it can be handed over out-of-band).
        $user->notify(new \App\Notifications\UserInvitation($token, $request->user()->name, $expires->toDayDateTimeString()));
        $inviteUrl = rtrim((string) config('app.url'), '/') . '/admin/invite/' . $token;

        return response()->json([
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'role']),
                'invite_url' => $inviteUrl,
                'expires_at' => $expires->toISOString(),
            ],
        ], 201);
    }

    /** F10: new token + expiry for a pending invitation; the old link stops working. */
    public function resendInvite(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasMinimumRole('admin') || $user->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['message' => 'User not found'], 404);
        }
        if (!$user->invitation_token) {
            return response()->json(['message' => 'This user has already accepted their invitation.'], 422);
        }
        $token = Str::random(64);
        $expires = now()->addHours(48);
        $user->forceFill(['invitation_token' => InviteController::hashToken($token), 'invitation_expires_at' => $expires])->save();
        $user->notify(new \App\Notifications\UserInvitation($token, $request->user()->name, $expires->toDayDateTimeString()));

        return response()->json(['data' => [
            'invite_url' => rtrim((string) config('app.url'), '/') . '/admin/invite/' . $token,
            'expires_at' => $expires->toISOString(),
        ]]);
    }

    /** F10: revoke a pending invitation — the placeholder account is removed. */
    public function revokeInvite(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasMinimumRole('admin') || $user->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['message' => 'User not found'], 404);
        }
        if (!$user->invitation_token) {
            return response()->json(['message' => 'This user has already accepted their invitation.'], 422);
        }
        $user->forceDelete(); // a never-used placeholder — hard delete so the email can be re-invited

        return response()->json(null, 204);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // The tenant owner's role cannot be changed by anyone via this endpoint
        // (mirrors destroy()'s owner protection — an admin must not demote the owner).
        if ($user->isOwner()) {
            return response()->json(['message' => 'The site owner\'s role cannot be changed.'], 403);
        }

        // Only owner can set admin/owner roles
        $request->validate([
            'role' => ['required', 'in:viewer,author,editor,admin'],
        ]);

        if ($request->input('role') === 'admin' && !$request->user()->isOwner()) {
            return response()->json(['message' => 'Only the owner can assign admin role'], 403);
        }

        $user->update(['role' => $request->input('role')]);

        return response()->json(['data' => $user->only(['id', 'name', 'email', 'role'])]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot remove yourself'], 422);
        }

        if ($user->isOwner()) {
            return response()->json(['message' => 'Cannot remove the site owner'], 422);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
