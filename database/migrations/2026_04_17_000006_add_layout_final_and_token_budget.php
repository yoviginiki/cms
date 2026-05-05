<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->jsonb('layout_final')->nullable()->after('curation_final');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->integer('monthly_token_budget')->default(2000000)->after('name');
            $table->integer('monthly_tokens_used')->default(0)->after('monthly_token_budget');
            $table->timestamp('token_usage_reset_at')->nullable()->after('monthly_tokens_used');
        });
    }

    public function down(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->dropColumn('layout_final');
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['monthly_token_budget', 'monthly_tokens_used', 'token_usage_reset_at']);
        });
    }
};
