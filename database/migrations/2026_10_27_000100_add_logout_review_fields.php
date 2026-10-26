<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logout (exception checkout) review:
 *   pending → (manager step 1: verified) → approved / rejected.
 *   Admin approves in one step; a manager needs step 1 (verify) + step 2 (confirm with password).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->timestamp('logout_requested_at')->nullable();
            $table->foreignId('logout_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('logout_verified_at')->nullable();
            $table->string('logout_note', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('decision_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('logout_verified_by');
            $table->dropColumn(['logout_requested_at', 'logout_verified_at', 'logout_note', 'reviewed_at', 'decision_seen_at']);
        });
    }
};
