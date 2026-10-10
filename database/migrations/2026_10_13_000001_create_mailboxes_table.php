<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                  // also the sender name of outgoing mails
            $table->string('email')->unique();

            // IMAP (incoming)
            $table->string('imap_host');
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption', 10)->default('ssl'); // ssl | tls | none
            $table->string('imap_username');
            $table->text('imap_password');                 // encrypted cast
            $table->string('imap_folder', 100)->default('INBOX');
            // What happens to processed mails: mark_read | move
            $table->string('processed_action', 10)->default('mark_read');
            $table->string('processed_folder', 100)->nullable();

            // SMTP (outgoing)
            $table->string('smtp_host');
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_encryption', 10)->default('tls'); // tls | ssl | none
            $table->string('smtp_username');
            $table->text('smtp_password');                 // encrypted cast

            $table->foreignId('default_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            // Used for tickets created internally or in the portal.
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table) {
            // Replies are sent through this mailbox.
            $table->foreignId('mailbox_id')->nullable()->after('group_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mailbox_id');
        });
        Schema::dropIfExists('mailboxes');
    }
};
