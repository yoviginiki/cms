# Authentication & Permissions

## Authentication

### Method: Laravel Sanctum (Cookie-based SPA)

The admin SPA authenticates via **Sanctum's cookie-based session auth** (not API tokens). This provides CSRF protection and leverages Laravel's session infrastructure.

**Configuration:** `bootstrap/app.php`

```php
$middleware->statefulApi();
$middleware->group('api', [
    EnsureFrontendRequestsAreStateful::class,
    SetTenantFromAuth::class,
    SubstituteBindings::class,
]);
```

### Login Flow

1. Frontend POSTs to `/api/v1/auth/login` with email + password
2. `AuthController@login` validates via `LoginRequest`
3. Rate limited: 5 attempts per minute (throttle key per IP+email)
4. On success: session regenerated, `last_login_at` updated
5. Returns user object with tenant relationship
6. Subsequent requests include session cookie automatically

### Logout

```
POST /api/v1/auth/logout
```

Invalidates session, regenerates CSRF token.

### Password Reset

```
POST /api/v1/auth/forgot-password  (sends reset email)
POST /api/v1/auth/reset-password   (validates token, sets new password)
```

Both throttled to 5 per minute.

---

## Role-Based Access Control

### Role Hierarchy

| Role | Level | Capabilities |
|------|-------|-------------|
| `viewer` | 0 | Read-only access to content |
| `author` | 1 | Create/edit own posts |
| `editor` | 2 | Edit all content, manage categories/tags |
| `admin` | 3 | Full content + user management + settings |
| `owner` | 4 | Everything including destructive operations |

### User Model Methods

```php
$user->isOwner(): bool         // role === 'owner'
$user->isAdmin(): bool         // role in ['owner', 'admin']
$user->isEditor(): bool        // role in ['owner', 'admin', 'editor']
$user->hasMinimumRole('editor'): bool  // numeric comparison
```

### User Management (admin+ only)

| Endpoint | Action |
|----------|--------|
| `GET /users` | List tenant users |
| `POST /users/invite` | Invite user (generates invitation_token) |
| `PUT /users/{user}/role` | Change role |
| `DELETE /users/{user}` | Remove user |

---

## Middleware

### SetTenantFromAuth

**File:** `app/Http/Middleware/SetTenantFromAuth.php`

Applied to all API requests. After Sanctum authenticates:
1. Reads `$user->tenant_id`
2. Sets PostgreSQL session variable: `SET app.current_tenant_id = '{uuid}'`
3. This activates Row-Level Security policies

### TenantScope

**File:** `app/Http/Middleware/TenantScope.php`

**Alias:** `tenant.scope`

Applied to tenant-scoped route groups. Ensures the authenticated user's tenant has access to the requested resources.

### EnsureRole

**File:** `app/Http/Middleware/EnsureRole.php`

**Alias:** `role`

Usage: `->middleware('role:admin')` -- ensures minimum role level.

### SecurityHeaders

**File:** `app/Http/Middleware/SecurityHeaders.php`

Applied globally. Adds security headers (X-Frame-Options, CSP, etc.).

---

## Authorization Policies

**Path:** `app/Policies/`

The system uses Laravel Policies for fine-grained authorization. Controllers call `$this->authorize()` before actions.

Example from `SiteController`:
```php
$this->authorize('viewAny', Site::class);
$this->authorize('view', $site);
$this->authorize('create', Site::class);
$this->authorize('update', $site);
$this->authorize('delete', $site);
```

---

## Row-Level Security (PostgreSQL)

### Overview

When using PostgreSQL (`DB_CONNECTION=pgsql`), the system enables database-level tenant isolation through RLS policies. This provides defense-in-depth: even if application-level scoping has a bug, the database won't return data from other tenants.

### RLS Manager

**File:** `app/Domain/Database/RlsManager.php`

```php
RlsManager::enable()    // Creates policies
RlsManager::disable()   // Removes policies
RlsManager::isSupported()  // true only for pgsql
```

### Protected Tables

| Table | Isolation Column | Policy Type |
|-------|-----------------|-------------|
| `users` | `tenant_id` | Direct tenant match |
| `sites` | `tenant_id` | Direct tenant match |
| `pages` | `site_id` | Via sites.tenant_id |
| `posts` | `site_id` | Via sites.tenant_id |
| `categories` | `site_id` | Via sites.tenant_id |
| `assets` | `site_id` | Via sites.tenant_id |
| `deployments` | `site_id` | Via sites.tenant_id |
| `themes` | `site_id` | Via sites.tenant_id (NULL allowed for system themes) |

### Policy SQL

Direct tenant tables:
```sql
CREATE POLICY tenant_isolation ON users
USING (tenant_id = current_setting('app.current_tenant_id')::uuid)
```

Site-scoped tables:
```sql
CREATE POLICY tenant_isolation ON pages
USING (site_id IN (
    SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id')::uuid
))
```

### RLS Context Setting

The `SetTenantFromAuth` middleware sets:
```sql
SET app.current_tenant_id = '{tenant-uuid}'
```

For queue jobs (like PublishSiteJob), the job manually sets this before model access:
```php
DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");
```

---

## Application-Level Tenant Scoping

### TenantScoped Trait

**File:** `app/Domain/Concerns/TenantScoped.php`

For models with a direct `tenant_id` column:
- Adds a global scope filtering by `tenant_id`
- Auto-fills `tenant_id` on model creation
- Resolves tenant from `Auth::user()->tenant_id`

### SiteScoped Trait

**File:** `app/Domain/Concerns/SiteScoped.php`

For models scoped through their parent site (no direct `tenant_id`).

---

## Security Features

### Rate Limiting

| Endpoint | Limit |
|----------|-------|
| Login | 5 per minute |
| Password reset | 5 per minute |
| Form submissions | 10 per minute |
| AI endpoints | 20 per minute |
| Analytics tracking | 60 per minute |

### CSRF Protection

Sanctum's stateful API mode requires a valid CSRF token for state-changing requests. The frontend fetches the CSRF cookie via `/sanctum/csrf-cookie` before login.

### Advisory Locks

`AdvisoryLock::run("key", $callback)` prevents concurrent operations (e.g., simultaneous publishes for the same site).

### Input Validation

All mutation endpoints use dedicated Form Request classes in `app/Http/Requests/` for validation. Block content is additionally sanitized via `SanitizationService` (HTMLPurifier) before rendering.
