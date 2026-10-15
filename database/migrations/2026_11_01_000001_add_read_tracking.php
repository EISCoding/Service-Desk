<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unread markers: when the customer and the team last wrote, and when each
 * staff member or contact last opened a ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('customer_replied_at')->nullable()->after('last_activity_at');
            $table->timestamp('team_replied_at')->nullable()->after('customer_replied_at');
        });

        Schema::create('ticket_reads', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('reader_type', 20);
            $table->unsignedBigInteger('reader_id');
            $table->timestamp('read_at');

            $table->primary(['ticket_id', 'reader_type', 'reader_id']);
            $table->index(['reader_type', 'reader_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_reads');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['customer_replied_at', 'team_replied_at']);
        });
    }
};
