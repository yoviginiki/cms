<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency ledger for module receiving endpoints. A repeated request with
 * the same key + same payload hash returns the already-created draft rather than
 * making a duplicate. Tenant-scoped (written inside the token's tenant context)
 * → standard FORCE row-level security. See docs decision IDEMPOTENCY.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->uuid('tenant_id');
            $table->string('idempotency_key', 191);
            $table->string('payload_hash', 64);         // sha256 hex of the raw body
            $table->string('external_id', 191);         // the created draft's id
            $table->string('entity_type', 40)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->timestamps();

            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['module_id', 'tenant_id', 'idempotency_key'], 'uq_module_idem_key');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE module_idempotency_keys ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE module_idempotency_keys FORCE ROW LEVEL SECURITY');
        $own = "tenant_id = current_setting('app.current_tenant_id', true)::uuid";
        DB::statement("
            CREATE POLICY tenant_isolation ON module_idempotency_keys
            FOR ALL
            USING ({$own})
            WITH CHECK ({$own})
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON module_idempotency_keys');
        }
        Schema::dropIfExists('module_idempotency_keys');
    }
};
