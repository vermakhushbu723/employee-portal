<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leave rule per employee: "X leave(s) for every Y working days" (Earned Leave grows with days worked)
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('leave_count', 4, 1)->nullable()->after('monthly_salary');
            $table->unsignedSmallInteger('leave_per_days')->nullable()->after('leave_count');
        });

        // E-mail templates (admin manages them; used in every "Send Email" popup)
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('category', 40)->default('general');
            $table->string('subject', 200);
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // E-mails sent from the portal outside a lead (Meetings / Email page), for the history list
        Schema::create('sent_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 500);
            $table->string('subject', 200);
            $table->text('body');
            $table->string('status', 20)->default('sent');
            $table->timestamps();
        });

        $now = now();
        $t = fn ($name, $cat, $subject, $body) => ['name' => $name, 'category' => $cat, 'subject' => $subject, 'body' => $body, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
        DB::table('email_templates')->insert([
            $t('Meeting Invitation', 'meeting', 'Meeting invitation: {Title} on {Date}', "Hi {Name},\n\nYou are invited to a meeting with us.\n\nTopic: {Title}\nDate & time: {Date}, {Time}\nJoin here: {Link}\n\nThe link opens in your browser — no app or login needed.\n\nRegards,\n{Employee}\n{Company}"),
            $t('Meeting Reminder', 'meeting', 'Reminder: our meeting today at {Time}', "Hi {Name},\n\nA quick reminder of our meeting \"{Title}\" on {Date} at {Time}.\nJoin here: {Link}\n\nSee you soon,\n{Employee}\n{Company}"),
            $t('Meeting Rescheduled', 'meeting', 'Meeting moved to {Date}, {Time}', "Hi {Name},\n\nOur meeting \"{Title}\" has been moved to {Date} at {Time}.\nThe join link stays the same: {Link}\n\nSorry for any inconvenience.\n\nRegards,\n{Employee}\n{Company}"),
            $t('Interview Invitation', 'interview', 'Interview invitation — {Title}', "Dear {Name},\n\nThank you for your interest. We would like to invite you for an interview.\n\nRole: {Title}\nDate & time: {Date}, {Time}\nJoin here: {Link}\n\nPlease keep your resume and ID ready.\n\nBest regards,\n{Employee}\nHR Team, {Company}"),
            $t('Follow-up', 'sales', 'Following up on our conversation', "Hi {Name},\n\nThank you for your time. I am following up on our discussion about {Purpose}.\nPlease let me know a good time to talk, or reply to this e-mail with any questions.\n\nRegards,\n{Employee}\n{Company}"),
            $t('Thank You', 'sales', 'Thank you for meeting with us', "Hi {Name},\n\nThank you for meeting with us today. As discussed, we will share the next steps shortly.\n\nRegards,\n{Employee}\n{Company}"),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_emails');
        Schema::dropIfExists('email_templates');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['leave_count', 'leave_per_days']);
        });
    }
};
