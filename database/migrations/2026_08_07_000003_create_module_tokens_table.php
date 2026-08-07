<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bearer tokens that external module services (e.g. the standalone ArtDay
 * Culture Engine) use to authenticate against module receiving endpoints.
 *
 * tenant_id is NULLABLE: null = platform-level token, set = scoped to one
 * tenant. Only the hash is persisted; the plaintext is shown exactly once on
 * creation.
 *
 * NO tenant row-level security — this is an auth-credential table, looked up by
 * hash BEFORE any tenant context can exist (the token itself identifies the
 * tenant). It deliberately mirrors Sanctum's `personal_access_tokens`, which is
 * not tenant-RLS'd for the same auth-bootstrap reason. See docs decision
 * RLS-TOKENS. The `AuthModuleToken` guard sets `app.current_tenant_id` from the
 * matched token's tenant_id after lookup; management queries filter by tenant_id
 * explicitly and are RBAC-gated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->uuid('tenant_id')->nullable();          // null = platform-level
            $table->string('name', 160);
            $table->string('token_hash', 64)->unique();     // sha256 hex; hash only
            $table->jsonb('abilities')->default('[]');      // e.g. ["drafts:create"]
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('token_hash');
            $table->index(['module_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_tokens');
    }
};
