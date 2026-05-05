<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->uuid('site_id');
            $table->string('path', 500);
            $table->string('referrer', 500)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('device')->nullable(); // desktop, mobile, tablet
            $table->string('browser')->nullable();
            $table->timestamp('viewed_at')->useCurrent();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->index(['site_id', 'viewed_at']);
            $table->index(['site_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
