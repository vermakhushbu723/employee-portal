<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - departments.for_data: departments that calling data is uploaded for (admin can create more)
 * - roles + users.custom_role_id: admin-made roles (e.g. "Recruiter") on top of a base portal role
 * - shifts.is_custom: shifts created from the employee form (custom start / end / hours)
 * - work_assignments: work given with a shared file (calling task) or a lead-creation task,
 *   with the admin's work message and targets
 * - attendance_sessions.target_*: the day's targets locked in when the day is closed
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('for_data')->default(false)->after('status');
        });
        DB::table('departments')->whereIn('code', ['RE', 'LOAN', 'CC', 'HRR'])->update(['for_data' => true]);

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('base_role', 30);          // super_admin | hr | manager | team_leader | employee
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('custom_role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false)->after('is_default');
        });

        Schema::create('work_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('lead_batches')->nullOnDelete();
            $table->string('type', 20);                 // calling | lead_create
            $table->string('title');                    // file name or task title
            $table->text('message')->nullable();        // work details from admin / manager
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('interested_target')->default(0);
            $table->unsignedInteger('lead_target')->default(0);
            $table->timestamp('assigned_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'completed_at']);
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('target_leads')->nullable()->after('interested_count');
            $table->unsignedSmallInteger('target_interested')->nullable()->after('target_leads');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', fn (Blueprint $t) => $t->dropColumn(['target_leads', 'target_interested']));
        Schema::dropIfExists('work_assignments');
        Schema::table('shifts', fn (Blueprint $t) => $t->dropColumn('is_custom'));
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('custom_role_id'));
        Schema::dropIfExists('roles');
        Schema::table('departments', fn (Blueprint $t) => $t->dropColumn('for_data'));
    }
};
