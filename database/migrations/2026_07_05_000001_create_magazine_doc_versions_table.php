<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Version trail for DTP magazine documents (W3 autosave+versions):
        // one full-document snapshot per save, capped per issue in
        // DtpDocumentService. Restore = feed .document back into saveDocument.
        Schema::create('magazine_doc_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('issue_id');
            $table->string('label')->nullable();
            $table->jsonb('document');
            $table->integer('page_count')->default(0);
            $table->integer('frame_count')->default(0);
            $table->timestamp('created_at');

            $table->foreign('issue_id')->references('id')->on('magazine_issues')->cascadeOnDelete();
            $table->index(['issue_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magazine_doc_versions');
    }
};
