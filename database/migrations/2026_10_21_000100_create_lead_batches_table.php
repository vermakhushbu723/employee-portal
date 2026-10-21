<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data upload & share: admin uploads a calling list (CSV / Excel) as a
 * batch of leads, then shares (assigns) them to employees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('invalid')->default(0);
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('id')->constrained('lead_batches')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropConstrainedForeignId('batch_id');
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn('assigned_at');
        });

        Schema::dropIfExists('lead_batches');
    }
};
