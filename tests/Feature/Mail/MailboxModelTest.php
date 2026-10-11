<?php

use App\Models\Activity;
use App\Models\Group;
use App\Models\Mailbox;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

it('stores IMAP and SMTP passwords encrypted', function () {
    $mailbox = Mailbox::factory()->create(['imap_password' => 'imap-geheim', 'smtp_password' => 'smtp-geheim']);

    $raw = DB::table('mailboxes')->where('id', $mailbox->id)->first();

    expect($raw->imap_password)->not->toContain('imap-geheim')
        ->and($raw->smtp_password)->not->toContain('smtp-geheim')
        ->and($mailbox->refresh()->imap_password)->toBe('imap-geheim')
        ->and($mailbox->smtp_password)->toBe('smtp-geheim')
        ->and($mailbox->toArray())->not->toHaveKeys(['imap_password', 'smtp_password']);
});

it('never writes passwords into the activity log', function () {
    $mailbox = Mailbox::factory()->create(['smtp_password' => 'alt-geheim']);
    $mailbox->update(['smtp_password' => 'neu-geheim']);

    $json = Activity::where('subject_type', 'mailbox')->get()->toJson();

    expect($json)->not->toContain('geheim')
        ->and($mailbox->activities()->first()->changes['smtp_password'])->toBe(['old' => Activity::MASKED, 'new' => Activity::MASKED]);
});

it('has sensible defaults', function () {
    $mailbox = Mailbox::factory()->create()->refresh();

    expect($mailbox)
        ->imap_port->toBe(993)->imap_encryption->toBe('ssl')->imap_folder->toBe('INBOX')
        ->smtp_port->toBe(587)->smtp_encryption->toBe('tls')
        ->processed_action->toBe('mark_read')->is_active->toBeTrue()->is_default->toBeFalse();
});

it('has a default group and tickets', function () {
    $group = Group::factory()->create();
    $mailbox = Mailbox::factory()->create();
    $mailbox->defaultGroup()->associate($group)->save();
    Ticket::factory()->create()->mailbox()->associate($mailbox)->save();

    expect($mailbox->refresh()->defaultGroup->is($group))->toBeTrue()
        ->and($mailbox->tickets)->toHaveCount(1);
});

it('keeps exactly one default mailbox and falls back to the first active one', function () {
    $first = Mailbox::factory()->create();
    expect(Mailbox::default()->is($first))->toBeTrue();

    $second = Mailbox::factory()->default()->create();
    $third = Mailbox::factory()->default()->create();

    expect($second->refresh()->is_default)->toBeFalse()
        ->and(Mailbox::default()->is($third))->toBeTrue();

    $third->update(['is_active' => false]);
    expect(Mailbox::default()->is($first))->toBeTrue();
});

it('lists its own addresses in lower case', function () {
    Mailbox::factory()->create(['email' => 'Support@Firma.test']);

    expect(Mailbox::addresses())->toBe(['support@firma.test']);
});
