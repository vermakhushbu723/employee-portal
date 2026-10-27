<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - leads: purpose of connect, department, next action (call / meeting / visit), review & correction tag
 * - lead_communications: calls (talk time), WhatsApp / e-mail sent from the portal, voice notes
 * - meetings: primary + other managers, who was notified, outcome (success / rejected / not connected)
 * - users.features: which employee sections a manager / team leader also gets (punch in/out, leads, meetings)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('purpose', 150)->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('next_action_at')->nullable();
            $table->string('next_action_type', 20)->nullable();   // call | meeting | visit | document
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->string('correction_note', 500)->nullable();
            $table->index(['department_id', 'status']);
        });

        // Existing leads: department from their uploaded file
        DB::table('leads')->whereNotNull('batch_id')->orderBy('id')->chunkById(500, function ($leads) {
            $depts = DB::table('lead_batches')->whereIn('id', $leads->pluck('batch_id')->unique())->pluck('department_id', 'id');
            foreach ($leads as $l) {
                if ($depts[$l->batch_id] ?? null) {
                    DB::table('leads')->where('id', $l->id)->update(['department_id' => $depts[$l->batch_id]]);
                }
            }
        });

        Schema::create('lead_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);                         // call | whatsapp | email | voice_note
            $table->string('status', 20)->default('sent');         // sent | failed | done
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable(); // call talk time / voice note length
            $table->string('file_path')->nullable();               // voice note / e-mail attachment
            $table->timestamps();
            $table->index(['lead_id', 'created_at']);
            $table->index(['user_id', 'channel', 'created_at']);
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('participant');    // primary | participant
            $table->timestamps();
            $table->unique(['meeting_id', 'user_id']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->string('outcome', 20)->nullable();             // success | rejected | not_connected
            $table->string('outcome_note', 500)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('notified')->nullable();                  // [{to, channel, ok}]
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('features')->nullable();                  // ['attendance', 'leads', 'meetings']
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('features'));
        Schema::table('meetings', fn (Blueprint $t) => $t->dropColumn(['outcome', 'outcome_note', 'duration_seconds', 'notified']));
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('lead_communications');
        Schema::table('leads', function (Blueprint $t) {
            $t->dropIndex(['department_id', 'status']);
            $t->dropConstrainedForeignId('department_id');
            $t->dropConstrainedForeignId('corrected_by');
            $t->dropColumn(['purpose', 'next_action_at', 'next_action_type', 'corrected_at', 'correction_note']);
        });
    }
};
