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

        // Set slug for existing themes (use DB facade — Theme model has SoftDeletes
        // which references deleted_at, added in a later migration)
        \Illuminate\Support\Facades\DB::table('themes')
            ->whereNull('slug')
            ->orderBy('id')
            ->each(function ($theme) {
                \Illuminate\Support\Facades\DB::table('themes')
                    ->where('id', $theme->id)
                    ->update(['slug' => \Illuminate\Support\Str::slug($theme->name)]);
            });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn(['slug', 'is_active']);
        });
    }
};
