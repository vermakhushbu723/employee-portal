<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Check-in window + late check-in approval.
 *  - Check-in opens a few minutes before shift start (checkin_opens_minutes)
 *    and is on time until start + grace_minutes.
 *  - After that the employee must send a late check-in request and an
 *    admin must approve it before check-in is possible.
 * The default shift becomes 10:00–19:00 with a 09:55–10:15 check-in window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->unsignedSmallInteger('checkin_opens_minutes')->default(5)->after('grace_minutes');
        });

        DB::table('shifts')->where('is_default', true)->update([
            'start_time' => '10:00:00',
            'end_time' => '19:00:00',
            'grace_minutes' => 15,
            'checkin_opens_minutes' => 5,
        ]);

        Schema::create('late_checkin_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();   // pending | approved | rejected
            $table->text('rejection_reason')->nullable();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('actioned_at')->nullable();
            $table->foreignId('attendance_session_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_checkin_requests');

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('checkin_opens_minutes');
        });
    }
};
