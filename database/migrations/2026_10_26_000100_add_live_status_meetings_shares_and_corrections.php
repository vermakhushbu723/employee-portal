<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Latest device status sent with the heartbeat (battery, location, last real activity)
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_active_at')->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->boolean('battery_charging')->nullable();
            $table->string('device_type', 20)->nullable();          // mobile | desktop
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('location_accuracy')->nullable(); // metres
            $table->timestamp('location_at')->nullable();
            $table->string('location_status', 20)->nullable();      // ok | denied | unsupported
            $table->timestamp('device_at')->nullable();
        });

        // Location trail for Live Tracking (one point every few minutes while punched in)
        Schema::create('location_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('accuracy')->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->timestamp('recorded_at');
            $table->index(['user_id', 'recorded_at']);
        });

        // Customer meetings (Google Meet / Zoom / Teams links)
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();          // employee who attends
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_mobile', 20)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('title');
            $table->string('platform', 20)->default('google_meet');                // google_meet | zoom | teams | other
            $table->string('link', 500)->nullable();
            $table->timestamp('scheduled_at');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('upcoming');                     // pending | upcoming | live | completed | declined | cancelled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'scheduled_at']);
            $table->index(['status', 'scheduled_at']);
        });

        // Each "Share" action, so it can be listed and rolled back
        Schema::create('data_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('lead_batches')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message')->nullable();
            $table->unsignedInteger('interested_target')->default(0);
            $table->string('mode', 10);
            $table->unsignedInteger('total')->default(0);
            $table->json('recipients')->nullable();                                 // {user_id: count}
            $table->timestamp('rolled_back_at')->nullable();
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('rolled_back_count')->default(0);
            $table->timestamps();
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('share_id')->nullable()->constrained('data_shares')->nullOnDelete();
        });
        Schema::table('work_assignments', function (Blueprint $table) {
            $table->foreignId('share_id')->nullable()->constrained('data_shares')->nullOnDelete();
        });

        // Work data correction → review → approve / reject (with full history)
        Schema::create('work_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();          // employee
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('original');
            $table->json('corrected');
            $table->string('reason', 500);
            $table->text('details');
            $table->string('status', 20)->default('pending');                     // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->unsignedInteger('calls_made')->nullable();
            $table->timestamp('corrected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', fn (Blueprint $t) => $t->dropColumn(['calls_made', 'corrected_at']));
        Schema::dropIfExists('work_corrections');
        Schema::table('work_assignments', fn (Blueprint $t) => $t->dropConstrainedForeignId('share_id'));
        Schema::table('leads', fn (Blueprint $t) => $t->dropConstrainedForeignId('share_id'));
        Schema::dropIfExists('data_shares');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('location_logs');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn([
            'last_active_at', 'battery_level', 'battery_charging', 'device_type', 'latitude', 'longitude',
            'location_accuracy', 'location_at', 'location_status', 'device_at',
        ]));
    }
};
