<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            if (!Schema::hasColumn('themes', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (!Schema::hasColumn('themes', 'is_active')) {
                $table->boolean('is_active')->default(false)->after('is_system');
            }
        });

        // Set slug for existing themes
        \App\Models\Theme::whereNull('slug')->each(function ($theme) {
            $theme->update(['slug' => \Illuminate\Support\Str::slug($theme->name)]);
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn(['slug', 'is_active']);
        });
    }
};
