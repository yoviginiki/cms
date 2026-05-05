<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('business');
            $table->string('preview_image')->nullable();
            $table->jsonb('template_data');
            $table->integer('page_count')->default(0);
            $table->boolean('is_public')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index(['category', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_templates');
    }
};
