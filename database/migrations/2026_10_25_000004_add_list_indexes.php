<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the ticket list views ("Meine", "Meine Gruppen",
 * customer tab, portal) which filter by owner and status and sort by activity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['assignee_id', 'status', 'last_activity_at']);
            $table->index(['group_id', 'status', 'last_activity_at']);
            $table->index(['customer_id', 'status', 'last_activity_at']);
            $table->index(['status', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['assignee_id', 'status', 'last_activity_at']);
            $table->dropIndex(['group_id', 'status', 'last_activity_at']);
            $table->dropIndex(['customer_id', 'status', 'last_activity_at']);
            $table->dropIndex(['status', 'last_activity_at']);
        });
    }
};
