<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2000);
            $table->jsonb('events')->default('[]');
            $table->string('secret', 64);
            $table->boolean('active')->default(true);
            $table->timestamp('last_delivered_at')->nullable();
            $table->integer('last_status')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('webhook_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->string('event', 60);
            $table->jsonb('payload')->default('{}');
            $table->string('status', 20)->default('pending'); // pending | delivered | failed
            $table->unsignedInteger('attempts')->default(0);
            $table->integer('response_code')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_attempt_at']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['webhooks', 'webhook_deliveries'] as $t) {
            DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
            $own = "site_id IN (SELECT id FROM sites WHERE tenant_id = current_setting('app.current_tenant_id', true)::uuid)";
            DB::statement("
                CREATE POLICY tenant_isolation ON {$t}
                FOR ALL
                USING ({$own})
                WITH CHECK ({$own})
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON webhook_deliveries');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON webhooks');
        }
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
