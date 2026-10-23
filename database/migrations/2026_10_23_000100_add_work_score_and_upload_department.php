<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Work-based attendance:
 *   score = average of (active time / shift time, leads created / target,
 *   interested+converted / target). The day's grade (full / half / quarter /
 *   none) is stored on the attendance session, plus admin corrections and
 *   when the daily report mail was sent.
 * Data upload: each upload belongs to a department and a manager.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('leads_created')->default(0)->after('active_seconds');
            $table->unsignedSmallInteger('interested_count')->default(0)->after('leads_created');
            $table->unsignedTinyInteger('work_score')->nullable()->after('interested_count');
            $table->string('grade', 10)->nullable()->after('work_score');          // full | half | quarter | none
            $table->foreignId('corrected_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->text('correction_note')->nullable()->after('corrected_by');
            $table->timestamp('report_sent_at')->nullable()->after('correction_note');
        });

        Schema::table('lead_batches', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('name')->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
        });

        // Days that were already closed keep their existing result (no active-time data existed then)
        foreach (['present' => 'full', 'late' => 'full', 'half_day' => 'half', 'absent' => 'none'] as $status => $grade) {
            DB::table('attendance_sessions')->whereNotNull('check_out_at')->where('status', $status)->update(['grade' => $grade]);
        }

        // Business departments used for data upload
        foreach ([['Real Estate', 'RE'], ['Loan', 'LOAN'], ['Credit Card', 'CC'], ['HR & Recruitment', 'HRR']] as [$name, $code]) {
            if (! DB::table('departments')->where('name', $name)->exists()) {
                DB::table('departments')->insert(['name' => $name, 'code' => $code, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('lead_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('manager_id');
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrected_by');
            $table->dropColumn(['leads_created', 'interested_count', 'work_score', 'grade', 'correction_note', 'report_sent_at']);
        });
    }
};
