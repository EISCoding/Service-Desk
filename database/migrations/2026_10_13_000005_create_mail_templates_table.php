<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            // system: automatic mails (ticket opened, status changed, …); snippet: text blocks for replies
            $table->string('type', 10)->index();
            // Fixed key of system templates (ticket_opened, status_changed, …)
            $table->string('key', 50)->nullable()->unique();
            $table->string('name', 150);
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
};
