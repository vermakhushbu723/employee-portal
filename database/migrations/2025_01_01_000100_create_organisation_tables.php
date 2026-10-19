<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departments, shifts and the employee profile that links a user
 * to a department, shift and reporting manager.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->nullable()->unique();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('required_minutes')->default(480);
            $table->unsignedSmallInteger('break_allowed_minutes')->default(60);
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('designation')->nullable();
            $table->string('work_type', 30)->default('work_from_home');
            $table->date('joining_date')->nullable();
            $table->decimal('monthly_salary', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('departments');
    }
};
