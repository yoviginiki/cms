<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paid-banner click counters for the magazine viewer (backlog pack 2).
        // Written by an unauthenticated throttled beacon — keep it dumb: no
        // PII, just issue + href + timestamp.
        Schema::create('mag_ad_clicks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('issue_id');
            $table->string('href', 500);
            $table->timestamp('created_at');

            $table->foreign('issue_id')->references('id')->on('magazine_issues')->cascadeOnDelete();
            $table->index(['issue_id', 'href']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mag_ad_clicks');
    }
};
