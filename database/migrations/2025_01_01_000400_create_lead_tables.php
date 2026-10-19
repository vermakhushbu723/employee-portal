<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('mobile', 15)->unique();
            $table->string('alternate_mobile', 15)->nullable();
            $table->string('email')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('source', 50);
            $table->string('interest', 100);
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->date('follow_up_date')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                // created | status_change | follow_up
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('leads');
    }
};
