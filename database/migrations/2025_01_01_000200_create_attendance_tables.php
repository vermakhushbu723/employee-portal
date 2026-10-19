<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One attendance session per employee per day, plus the breaks taken
 * inside it. All times are server timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->timestamp('check_in_at');
            $table->timestamp('check_out_at')->nullable();
            $table->string('status', 20)->default('present');
            $table->boolean('is_late')->default(false);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->string('checkout_type', 20)->nullable();   // normal | exception
            $table->text('checkout_reason')->nullable();
            $table->string('review_status', 20)->nullable();   // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
        });

        Schema::create('break_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_sessions');
        Schema::dropIfExists('attendance_sessions');
    }
};
