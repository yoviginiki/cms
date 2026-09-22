<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asset library folders. `assets.folder` (plain path string, "lampi/2026")
 * already exists since 2026_04_16_000030 but was never used; asset_folders
 * only exists so an EMPTY folder can be created before anything is uploaded
 * into it. Folder listing = union of both (see AssetFolderController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('site_id');
            $table->string('path');
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->unique(['site_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_folders');
    }
};
