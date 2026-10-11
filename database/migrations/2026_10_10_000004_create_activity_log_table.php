<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            // Who did it: a staff user now, a portal contact from Phase 5 on; null for system jobs.
            $table->nullableMorphs('causer');
            // What it was done to.
            $table->nullableMorphs('subject');
            $table->string('action', 50);
            // {"field": {"old": …, "new": …}}
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
