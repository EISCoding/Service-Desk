<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            // public | internal | mail_in | mail_out
            $table->string('type', 10)->index();
            // Sanitized HTML
            $table->longText('body');
            // Staff user or contact; null for unknown mail senders.
            $table->nullableMorphs('author');
            // Mail threading (Phase 4)
            $table->string('message_id')->nullable()->index();
            $table->string('in_reply_to')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
