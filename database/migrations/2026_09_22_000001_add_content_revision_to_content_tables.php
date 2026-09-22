<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F13 (audit 2026-09-22) — explicit content revision on the owning record.
 * The previous optimistic-concurrency token was count:max(updated_at) of the
 * blocks (repeatable within a second, and checked outside the transaction).
 * `content_revision` is compared-and-incremented in the SAME transaction as
 * the block rewrite, so two editors saving from the same revision get exactly
 * one success and one 409, never a mixed tree.
 */
return new class extends Migration
{
    private const TABLES = ['pages', 'posts', 'theme_templates'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasColumn($table, 'content_revision')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('content_revision')->default(0);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'content_revision')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('content_revision');
                });
            }
        }
    }
};
