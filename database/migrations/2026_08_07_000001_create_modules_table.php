<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Framework — the registry of optional, on/off-controllable modules
 * (e.g. `culture-engine`). This table is PLATFORM-GLOBAL: it lists which
 * modules exist and whether they are enabled across the whole platform. It
 * carries NO tenant_id and therefore NO row-level security — every tenant sees
 * the same catalogue; per-tenant enablement lives in `module_tenant`.
 *
 * Resolution rule (ModuleRegistry): a module is effectively enabled for a
 * tenant iff `enabled_globally` AND the tenant's `module_tenant.enabled`.
 * Permission checks are separate (role-hierarchy gates, see docs decision RBAC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 100)->unique();          // e.g. culture-engine
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('enabled_globally')->default(false);
            $table->jsonb('settings_schema')->nullable();   // form schema for tenant settings
            $table->timestamps();

            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
