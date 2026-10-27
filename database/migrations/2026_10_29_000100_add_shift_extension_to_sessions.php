<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inactive time extends the shift (capped); the extension is frozen when the actual shift ends,
 * and the employee is punched out automatically at the extended end (checkout_type = auto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->unsignedInteger('extension_seconds')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', fn (Blueprint $t) => $t->dropColumn('extension_seconds'));
    }
};
