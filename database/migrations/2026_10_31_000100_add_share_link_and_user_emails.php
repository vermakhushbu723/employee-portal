<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "New Calling Data Assigned" notices know their share, so a rollback / edit can remove or update them
        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('share_id')->nullable()->after('user_id')->constrained('data_shares')->nullOnDelete();
        });

        // Up to 4 e-mail IDs per user, verified with an OTP (My Profile → My E-mail IDs)
        Schema::create('user_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email', 150);
            $table->string('otp_hash')->nullable();
            $table->dateTime('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->dateTime('otp_sent_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'email']);
        });

        // Old review notices said "by Admin": employees see "Our Management Team"
        \Illuminate\Support\Facades\DB::table('announcements')->where('title', 'Lead reviewed & corrected by Admin')
            ->update(['title' => 'Lead reviewed & corrected by Our Management Team']);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_emails');
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('share_id');
        });
    }
};
