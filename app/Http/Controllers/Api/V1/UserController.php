<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // F10: the (hashed) invitation token never leaves the server — only the status.
        $users = User::where('tenant_id', $request->user()->tenant_id)
            ->select(['id', 'name', 'email', 'role', 'restricted_to_sites', 'last_login_at', 'invitation_token', 'invitation_expires_at', 'created_at'])
            ->orderBy('name')
            ->get();

        // Per-site grants in one query (site names come through RLS = this tenant).
        $grants = DB::table('site_user')
            ->join('sites', 'sites.id', '=', 'site_user.site_id')
            ->whereIn('site_user.user_id', $users->pluck('id'))
            ->whereNull('sites.deleted_at')
            ->orderBy('sites.name')
            ->get(['site_user.user_id', 'site_user.site_id', 'site_user.role', 'sites.name', 'sites.slug'])
            ->groupBy('user_id');

        $users = $users->map(fn($u) => [
            ...collect($u->toArray())->except(['invitation_token'])->all(),
            'restricted_to_sites' => $u->isRestrictedToSites(),
            'sites' => ($grants[$u->id] ?? collect())->map(fn($g) => [
                'site_id' => $g->site_id, 'name' => $g->name, 'slug' => $g->slug, 'role' => $g->role,
            ])->values(),
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
            ...$this->accessRules(),
        ]);

        // Only the owner may create another admin (parity with updateRole).
        if ($this->grantsAdmin($request) && !$request->user()->isOwner()) {
            return response()->json(['message' => 'Only the owner can invite an admin.'], 403);
        }
        $access = $this->resolveAccess($request);

        // Check if email already exists in tenant
        $existing = User::where('tenant_id', $request->user()->tenant_id)
            ->where('email', $request->input('email'))
            ->first();

        if ($existing) {
            return response()->json(['message' => 'A user with this email already exists.'], 422);
        }

        $token = Str::random(64);
        $expires = now()->addHours(48);

        $user = DB::transaction(function () use ($request, $token, $expires, $access) {
            $user = User::create([
                'tenant_id' => $request->user()->tenant_id,
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make(Str::random(32)), // Placeholder until they set their own
                'role' => $access['role'],
                'restricted_to_sites' => $access['restricted'],
                'invitation_token' => InviteController::hashToken($token), // F10: hashed at rest
                'invitation_expires_at' => $expires,
                'invited_by' => $request->user()->id,
            ]);
            $this->syncSites($user, $access);

            return $user;
        });

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

    /**
     * Create an active account directly (no invitation e-mail): the admin
     * sets the password and hands the credentials over themselves.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tenantId = $request->user()->tenant_id;
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role' => ['required', 'in:editor,admin,viewer,author'],
            ...$this->accessRules(),
        ]);

        if ($this->grantsAdmin($request) && !$request->user()->isOwner()) {
            return response()->json(['message' => 'Only the owner can create an admin.'], 403);
        }
        $access = $this->resolveAccess($request);

        $user = DB::transaction(function () use ($request, $tenantId, $access) {
            // users is UNIQUE(tenant_id, email) including soft-deleted rows: a
            // previously removed account with this e-mail is brought back
            // (keeps authorship links) rather than tripping the constraint.
            $user = User::onlyTrashed()->where('tenant_id', $tenantId)->where('email', $request->input('email'))->first()
                ?? new User(['tenant_id' => $tenantId]);
            $user->fill([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => $request->input('password'), // hashed by the model cast
                'role' => $access['role'],
                'restricted_to_sites' => $access['restricted'],
                'invited_by' => $request->user()->id,
                'invitation_token' => null,
                'invitation_expires_at' => null,
            ]);
            $user->deleted_at = null;
            $user->save();
            $this->syncSites($user, $access);

            return $user;
        });

        return response()->json(['data' => $user->only(['id', 'name', 'email', 'role', 'restricted_to_sites'])], 201);
    }

    /** Edit name / e-mail / password / role / site access of an existing user. */
    public function update(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        if (!$actor->hasMinimumRole('admin') || $user->tenant_id !== $actor->tenant_id) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // The owner account is only editable by the owner, and only its profile
        // (it always keeps tenant-wide owner access). Admin accounts are the
        // owner's to manage.
        if ($user->isOwner() && $actor->id !== $user->id) {
            return response()->json(['message' => 'Only the owner can edit the owner account.'], 403);
        }
        if ($user->role === 'admin' && !$actor->isOwner() && $actor->id !== $user->id) {
            return response()->json(['message' => 'Only the owner can edit an admin.'], 403);
        }

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->where('tenant_id', $user->tenant_id)->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
            'role' => ['sometimes', 'required', 'in:editor,admin,viewer,author'],
            ...$this->accessRules(),
        ]);

        $changesAccess = $request->hasAny(['role', 'restricted_to_sites', 'sites']);
        if ($changesAccess && $user->isOwner()) {
            return response()->json(['message' => 'The owner\'s access cannot be changed.'], 403);
        }
        if ($changesAccess && $actor->id === $user->id) {
            return response()->json(['message' => 'You cannot change your own access.'], 403);
        }
        if ($changesAccess && $this->grantsAdmin($request, $user) && !$actor->isOwner()) {
            return response()->json(['message' => 'Only the owner can grant admin.'], 403);
        }

        DB::transaction(function () use ($request, $user, $changesAccess) {
            $user->fill($request->only(['name', 'email']));
            if ($request->filled('password')) {
                $user->password = $request->input('password');
            }
            if ($changesAccess) {
                $access = $this->resolveAccess($request, $user);
                $user->role = $access['role'];
                $user->restricted_to_sites = $access['restricted'];
                $this->syncSites($user, $access);
            }
            $user->save();
        });

        return response()->json(['data' => $user->fresh()->only(['id', 'name', 'email', 'role', 'restricted_to_sites'])]);
    }

    /** Validation for the optional site-access part of create/invite/update. */
    private function accessRules(): array
    {
        return [
            'restricted_to_sites' => ['sometimes', 'boolean'],
            'sites' => ['sometimes', 'array', 'max:500'],
            'sites.*.site_id' => ['required', 'uuid', 'distinct'],
            'sites.*.role' => ['required', 'in:viewer,author,editor,admin'],
        ];
    }

    /** Would this request leave the user with admin anywhere (tenant-wide or on a site)? */
    private function grantsAdmin(Request $request, ?User $existing = null): bool
    {
        $restricted = $request->has('restricted_to_sites')
            ? $request->boolean('restricted_to_sites')
            : (bool) $existing?->restricted_to_sites;

        if ($restricted) {
            return collect($request->input('sites', []))->contains(fn($s) => ($s['role'] ?? null) === 'admin');
        }

        return $request->input('role', $existing?->role) === 'admin';
    }

    /**
     * Normalise the requested access. A restricted user's tenant-wide `role`
     * is derived from their site roles and capped at editor: it is what they
     * act with outside a site (AI helpers etc.), and it keeps tenant-wide
     * admin screens (users, modules, system) closed to them.
     *
     * @return array{restricted: bool, role: string, sites: array<string,string>}
     */
    private function resolveAccess(Request $request, ?User $existing = null): array
    {
        $restricted = $request->has('restricted_to_sites')
            ? $request->boolean('restricted_to_sites')
            : (bool) $existing?->restricted_to_sites;

        if (!$restricted) {
            return ['restricted' => false, 'role' => (string) $request->input('role', $existing?->role ?? 'editor'), 'sites' => []];
        }

        $sites = collect($request->has('sites') ? $request->input('sites', []) : ($existing?->siteRoles() ?? []))
            ->mapWithKeys(fn($v, $k) => is_array($v) ? [$v['site_id'] => $v['role']] : [$k => $v])
            ->all();

        if ($sites === []) {
            throw ValidationException::withMessages(['sites' => 'Pick at least one site for a user with limited access.']);
        }

        $known = Site::where('tenant_id', $request->user()->tenant_id)->whereIn('id', array_keys($sites))->pluck('id')->all();
        if (count($known) !== count($sites)) {
            throw ValidationException::withMessages(['sites' => 'One or more sites do not exist.']);
        }

        $levels = User::ROLE_HIERARCHY;
        $top = collect($sites)->sortByDesc(fn($r) => $levels[$r] ?? 0)->first();
        $role = ($levels[$top] ?? 0) > $levels['editor'] ? 'editor' : $top;

        return ['restricted' => true, 'role' => $role, 'sites' => $sites];
    }

    /** @param array{restricted: bool, sites: array<string,string>} $access */
    private function syncSites(User $user, array $access): void
    {
        $user->sites()->sync(collect($access['sites'])->map(fn($role) => ['role' => $role])->all());
        $user->forgetSiteRoles();
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

        if ($user->isRestrictedToSites()) {
            return response()->json(['message' => 'This user has per-site roles — edit their site access instead.'], 422);
        }

        $user->update(['role' => $request->input('role')]);

        return response()->json(['data' => $user->only(['id', 'name', 'email', 'role'])]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->tenant_id !== $request->user()->tenant_id) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot remove yourself'], 422);
        }

        if ($user->role === 'admin' && !$request->user()->isOwner()) {
            return response()->json(['message' => 'Only the owner can remove an admin.'], 403);
        }

        if ($user->isOwner()) {
            return response()->json(['message' => 'Cannot remove the site owner'], 422);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
