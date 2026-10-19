<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type', 20);
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 4, 1);
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('is_paid')->default(true);
            $table->text('rejection_reason')->nullable();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('leave_type', 20);
            $table->decimal('total', 4, 1)->default(0);
            $table->decimal('used', 4, 1)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'year', 'leave_type']);
        });

        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->date('record_date');
            $table->string('type', 20)->default('deduction');   // deduction | incentive
            $table->string('title');
            $table->string('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_records');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_requests');
    }
};
