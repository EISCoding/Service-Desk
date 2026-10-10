<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // The object whose history the entry belongs to as well, e.g. the
            // customer of a contact or note. Kept when the subject is deleted.
            $table->nullableMorphs('parent');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropMorphs('parent');
        });
    }
};
