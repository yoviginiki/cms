<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('invitation_token')->nullable()->after('last_login_at');
            $table->timestamp('invitation_expires_at')->nullable()->after('invitation_token');
            $table->uuid('invited_by')->nullable()->after('invitation_expires_at');

            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
            $table->dropColumn(['invitation_token', 'invitation_expires_at', 'invited_by']);
        });
    }
};
