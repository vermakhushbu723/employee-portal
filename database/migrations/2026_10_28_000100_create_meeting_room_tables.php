<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Built-in video meeting room (WebRTC, joins inside the dashboard).
 * Browsers talk to each other directly; the portal only passes the small
 * connection messages (offer / answer / candidates / chat) through these tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('room_token', 40)->nullable()->unique();   // customer join link: /meet/{token}
        });

        Schema::create('meeting_peers', function (Blueprint $table) {
            $table->string('id', 36)->primary();                      // random peer id
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->string('role', 20)->default('guest');               // host | staff | guest
            $table->string('secret', 64);
            $table->timestamp('last_seen_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->index(['meeting_id', 'last_seen_at']);
        });

        Schema::create('meeting_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('from_peer', 36);
            $table->string('to_peer', 36)->nullable();                  // null = everyone (chat)
            $table->string('type', 20);                                 // offer | answer | candidate | chat
            $table->longText('payload');
            $table->timestamp('created_at')->nullable();
            $table->index(['meeting_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_signals');
        Schema::dropIfExists('meeting_peers');
        Schema::table('meetings', fn (Blueprint $t) => $t->dropColumn('room_token'));
    }
};
