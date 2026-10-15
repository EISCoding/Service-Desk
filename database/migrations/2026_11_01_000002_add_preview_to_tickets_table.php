<?php

use App\Models\TicketMessage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Short text of the latest message, shown in the staff ticket list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('preview', 200)->nullable()->after('subject');
        });

        // Existing tickets: preview of their latest message written by a person.
        TicketMessage::query()
            ->whereIn('id', TicketMessage::query()->where('is_automatic', false)->selectRaw('max(id)')->groupBy('ticket_id'))
            ->with('author')
            ->chunkById(200, function ($messages) {
                foreach ($messages as $message) {
                    DB::table('tickets')->where('id', $message->ticket_id)->update(['preview' => $message->preview()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('preview');
        });
    }
};
