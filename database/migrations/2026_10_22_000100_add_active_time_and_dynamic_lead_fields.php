<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - attendance_sessions.active_seconds: time the employee was actually active
 *   on the portal (idle > 5 min is not counted), fed by browser heartbeats.
 * - leads.extra + lead_batches.columns: every column of an uploaded Excel/CSV
 *   is kept exactly as in the file, so each upload can have different fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->unsignedInteger('active_seconds')->default(0)->after('break_minutes');
            $table->timestamp('last_heartbeat_at')->nullable()->after('active_seconds');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->json('extra')->nullable()->after('remarks');
        });

        Schema::table('lead_batches', function (Blueprint $table) {
            $table->json('columns')->nullable()->after('file_name');
        });
    }

    public function down(): void
    {
        Schema::table('lead_batches', fn (Blueprint $table) => $table->dropColumn('columns'));
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('extra'));
        Schema::table('attendance_sessions', fn (Blueprint $table) => $table->dropColumn(['active_seconds', 'last_heartbeat_at']));
    }
};
