<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deploy_artifacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('deployment_id');
            $table->uuid('page_id')->nullable();
            $table->uuid('post_id')->nullable();
            $table->string('output_path');
            $table->string('content_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('deployment_id')->references('id')->on('deployments')->cascadeOnDelete();
            $table->foreign('page_id')->references('id')->on('pages')->nullOnDelete();
            $table->foreign('post_id')->references('id')->on('posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploy_artifacts');
    }
};
