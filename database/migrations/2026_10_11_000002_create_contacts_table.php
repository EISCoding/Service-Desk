<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('salutation', 10)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100);
            // Optional (phone-only contacts), required for portal access; unique when set.
            $table->string('email')->nullable()->unique();
            $table->string('phone', 50)->nullable();
            $table->string('position', 100)->nullable();
            $table->boolean('is_primary')->default(false);
            // Customer portal (guard "contact")
            $table->boolean('portal_access')->default(false);
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'is_primary']);
        });

        // Separate token table: staff and contacts may share an email address.
        Schema::create('contact_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_password_reset_tokens');
        Schema::dropIfExists('contacts');
    }
};
