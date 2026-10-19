<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A task is defined once and assigned to one or more employees.
 * Each assignment tracks that employee's own progress and history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 30)->index();
            $table->string('priority', 10)->default('medium');
            $table->unsignedInteger('target_quantity')->default(1);
            $table->string('unit', 30)->default('Tasks');
            $table->dateTime('deadline_at')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->json('rules')->nullable();
            $table->string('related_list')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('assigned_date')->index();
            $table->unsignedInteger('completed_quantity')->default(0);
            $table->string('status', 20)->default('assigned');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20);              // started | progress | submitted
            $table->unsignedInteger('quantity')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 20);              // calls | leads | follow_ups | conversions
            $table->string('period', 10)->default('monthly');
            $table->unsignedInteger('target_value');
            $table->timestamps();

            $table->unique(['user_id', 'metric', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('targets');
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('tasks');
    }
};
