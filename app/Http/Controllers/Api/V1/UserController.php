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

        $users = User::where('tenant_id', $request->user()->tenant_id)
            ->select(['id', 'name', 'email', 'role', 'last_login_at', 'invitation_token', 'created_at'])
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                ...$u->toArray(),
                'status' => $u->invitation_token ? 'pending' : 'active',
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

        $user = User::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make(Str::random(32)), // Placeholder until they set their own
            'role' => $request->input('role'),
            'invitation_token' => $token,
            'invitation_expires_at' => now()->addHours(48),
            'invited_by' => $request->user()->id,
        ]);

        // In production, this would send an email with the invite link
        // For now, return the token so admin can share the link
        $inviteUrl = config('app.url') . '/admin/invite/' . $token;

        return response()->json([
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'role']),
                'invite_url' => $inviteUrl,
            ],
        ], 201);
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
