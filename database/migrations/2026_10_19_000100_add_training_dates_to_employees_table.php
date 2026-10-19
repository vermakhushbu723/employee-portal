<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Training period (From – To) that comes before the joining date. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('training_from')->nullable()->after('work_type');
            $table->date('training_to')->nullable()->after('training_from');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['training_from', 'training_to']);
        });
    }
};
