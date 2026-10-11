<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // View the ticket list opens with (mine | groups | all | unassigned)
            $table->string('ticket_view', 20)->nullable()->after('signature');
            // {event: {database: bool, mail: bool}}, missing entries use the defaults
            $table->json('notification_settings')->nullable()->after('ticket_view');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ticket_view', 'notification_settings']);
        });
    }
};
