<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grids', function (Blueprint $table) {
            $table->string('container_padding', 50)->default('0 24px')->after('container_width');
            $table->string('min_height', 30)->nullable()->after('container_padding');
            $table->string('align_items', 20)->default('stretch')->after('min_height');
            $table->string('justify_items', 20)->default('stretch')->after('align_items');
            $table->string('overflow_x', 20)->default('visible')->after('justify_items');
            $table->string('layout_mode', 20)->default('default')->after('overflow_x');
            $table->jsonb('background_json')->nullable()->after('layout_mode');
            $table->boolean('full_bleed')->default(false)->after('background_json');
        });

        Schema::table('grid_positions', function (Blueprint $table) {
            $table->string('align_self', 20)->nullable()->after('min_height');
            $table->string('justify_self', 20)->nullable()->after('align_self');
            $table->string('max_width', 30)->nullable()->after('justify_self');
            $table->string('overflow', 20)->nullable()->after('max_width');
            $table->jsonb('border_json')->nullable()->after('padding_json');
            $table->string('shadow', 100)->nullable()->after('border_json');
            $table->string('css_class', 200)->nullable()->after('shadow');
            $table->boolean('full_bleed')->default(false)->after('css_class');
        });
    }

    public function down(): void
    {
        Schema::table('grids', function (Blueprint $table) {
            $table->dropColumn([
                'container_padding', 'min_height', 'align_items', 'justify_items',
                'overflow_x', 'layout_mode', 'background_json', 'full_bleed',
            ]);
        });

        Schema::table('grid_positions', function (Blueprint $table) {
            $table->dropColumn([
                'align_self', 'justify_self', 'max_width', 'overflow',
                'border_json', 'shadow', 'css_class', 'full_bleed',
            ]);
        });
    }
};
