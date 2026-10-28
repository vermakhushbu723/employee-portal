<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Meetings: planned end time, and the review admin / manager / TL gives after it (close / open / reschedule + next tag)
        Schema::table('meetings', function (Blueprint $table) {
            $table->dateTime('ends_at')->nullable()->after('scheduled_at');
            $table->string('tag', 30)->nullable()->after('outcome');
            $table->foreignId('reviewed_by')->nullable()->after('tag')->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
        });

        // Users: location mandatory for this ID + network of the device (live dashboard)
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('location_required')->default(false)->after('features');
            $table->string('network_type', 20)->nullable()->after('location_status');
            $table->decimal('network_mbps', 7, 2)->nullable()->after('network_type');
            $table->unsignedInteger('network_rtt')->nullable()->after('network_mbps');
        });

        // Existing meetings: 30 minutes long; no meeting closes by itself any more
        DB::table('meetings')->whereNull('ends_at')->update(['ends_at' => DB::raw(
            DB::getDriverName() === 'sqlite' ? "datetime(scheduled_at, '+30 minutes')" : 'DATE_ADD(scheduled_at, INTERVAL 30 MINUTE)'
        )]);

        // Employees see "Our Management Team" instead of the admin's name in old notifications
        DB::table('announcements')->where('body', 'like', 'Admin approved your late check-in%')
            ->update(['body' => DB::raw("REPLACE(body, 'Admin approved', 'Our Management Team approved')")]);
        $admins = DB::table('users')->whereIn('role', ['super_admin', 'hr'])->pluck('name');
        foreach ($admins as $name) {
            DB::table('announcements')->where('body', 'like', 'Approved by '.$name.'%')
                ->update(['body' => DB::raw('REPLACE(body, '.DB::getPdo()->quote('Approved by '.$name).", 'Approved by Our Management Team')")]);
        }
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['ends_at', 'tag', 'reviewed_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['location_required', 'network_type', 'network_mbps', 'network_rtt']);
        });
    }
};
