<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 12)->unique();
            $table->string('subject');
            $table->string('status', 20)->default('new')->index();
            $table->string('priority', 10)->default('normal')->index();
            // Customers with tickets cannot be deleted (only archived).
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            // Wiedervorlage: date while waiting internally/externally; flag set by the daily job.
            $table->date('follow_up_at')->nullable();
            $table->boolean('follow_up_due')->default(false)->index();
            $table->string('source', 10)->default('internal');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('merged_into_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
