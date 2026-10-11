<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Original sender of mail tickets (used when there is no contact).
            $table->string('requester_email')->nullable()->after('contact_id');
            $table->string('requester_name')->nullable()->after('requester_email');
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->string('from_email')->nullable()->after('author_id');
            $table->string('from_name')->nullable()->after('from_email');
            $table->json('to')->nullable()->after('from_name');
            $table->json('cc')->nullable()->after('to');
            // Outgoing mails: queued | sent | failed
            $table->string('mail_status', 10)->nullable()->after('in_reply_to');
            $table->timestamp('sent_at')->nullable()->after('mail_status');
            $table->text('mail_error')->nullable()->after('sent_at');
            // System template the mail was rendered from (e.g. ticket_opened)
            $table->string('template_key', 50)->nullable()->after('mail_error');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropColumn(['from_email', 'from_name', 'to', 'cc', 'mail_status', 'sent_at', 'mail_error', 'template_key']);
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['requester_email', 'requester_name']);
        });
    }
};
