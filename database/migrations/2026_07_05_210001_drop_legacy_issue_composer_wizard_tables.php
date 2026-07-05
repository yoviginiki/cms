<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy Issue Composer wizard is replaced by Issue Studio. All five
 * tables were confirmed EMPTY on production before this drop (2026-07-05).
 * magazine_issues is NOT touched — the DTP editor owns it now.
 */
return new class extends Migration
{
    public function up(): void
    {
        // children before parents: messages -> sessions; design_system has an
        // FK (source_run_id) onto curation_runs, so runs drop last
        Schema::dropIfExists('mag_wizard_messages');
        Schema::dropIfExists('mag_wizard_sessions');
        Schema::dropIfExists('issue_design_system');
        Schema::dropIfExists('issue_content_items');
        Schema::dropIfExists('magazine_curation_runs');
    }

    public function down(): void
    {
        // Irreversible: the legacy wizard code that owned these tables is gone.
    }
};
