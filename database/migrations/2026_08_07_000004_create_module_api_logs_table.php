<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for token-authenticated module requests. Deliberately separate
 * from `activity_logs` (which is user + site scoped and cannot represent a
 * user-less, site-less token request). No tenant RLS — auth-adjacent, may carry
 * a null tenant on a denied-auth request. See docs decision AUDIT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_api_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('module_id')->nullable();
            $table->uuid('module_token_id')->nullable();
            $table->uuid('tenant_id')->nullable();
            $table->string('method', 10);
            $table->string('path', 512);
            $table->string('ability', 100)->nullable();
            $table->string('decision', 40);        // granted | denied_auth | denied_ability | denied_module_disabled
            $table->smallInteger('status_code')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // Keep the audit row if the token is later deleted.
            $table->foreign('module_id')->references('id')->on('modules')->nullOnDelete();
            $table->foreign('module_token_id')->references('id')->on('module_tokens')->nullOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index(['tenant_id', 'created_at']);
            $table->index('module_token_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_api_logs');
    }
};
