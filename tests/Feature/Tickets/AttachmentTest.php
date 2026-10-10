<?php

use App\Models\Attachment;
use App\Models\Group;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\AttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->group = Group::factory()->create();
    $this->ticket = Ticket::factory()->create(['group_id' => $this->group->id]);
    $this->message = TicketMessage::factory()->for($this->ticket)->create();
    $this->member = User::factory()->staff()->create();
    $this->member->groups()->attach($this->group);
});

function storeAttachment(TicketMessage $message, string $name = 'rechnung.pdf', string $mime = 'application/pdf'): Attachment
{
    return app(AttachmentStorage::class)->storeUpload(UploadedFile::fake()->create($name, 120, $mime), $message);
}

it('stores files privately with a random name', function () {
    $attachment = storeAttachment($this->message);

    expect($attachment->path)->toStartWith('attachments/'.now()->format('Y/m').'/')
        ->and($attachment->path)->toEndWith('.pdf')
        ->and($attachment->path)->not->toContain('rechnung')
        ->and($attachment->original_name)->toBe('rechnung.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->size)->toBe(120 * 1024)
        ->and($attachment->humanSize())->toBe('120.0 KB')
        ->and($this->message->attachments)->toHaveCount(1);

    Storage::disk('local')->assertExists($attachment->path);
    expect(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'));
});

it('stores raw contents, e.g. from mails', function () {
    $attachment = app(AttachmentStorage::class)->storeContents('hallo', '../../etc/passwd.txt', 'text/plain', $this->message, 'cid123');

    expect($attachment->original_name)->toBe('passwd.txt')
        ->and($attachment->content_id)->toBe('cid123');
    Storage::disk('local')->assertExists($attachment->path);
});

it('lets staff who can see the ticket download the file', function () {
    $attachment = storeAttachment($this->message);

    $this->actingAs($this->member)
        ->get($attachment->url())
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertDownload('rechnung.pdf');
});

it('shows images and PDFs inline on request, other types never', function () {
    $pdf = storeAttachment($this->message);
    $html = storeAttachment($this->message, 'seite.html', 'text/html');

    $this->actingAs($this->member);
    expect($this->get($pdf->url(inline: true))->headers->get('Content-Disposition'))->toStartWith('inline');
    expect($this->get($html->url(inline: true))->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('forbids downloads for staff who cannot see the ticket', function () {
    $attachment = storeAttachment($this->message);

    $this->actingAs(User::factory()->staff()->create()) // not in the ticket's group
        ->get($attachment->url())
        ->assertForbidden();
});

it('requires a login', function () {
    $this->get(storeAttachment($this->message)->url())->assertRedirect('/intern/login');
});

it('removes the file with the attachment and with the ticket', function () {
    $first = storeAttachment($this->message);
    $second = storeAttachment($this->message);

    $first->delete();
    Storage::disk('local')->assertMissing($first->path);

    $this->ticket->delete();
    Storage::disk('local')->assertMissing($second->path);
    expect(Attachment::count())->toBe(0);
});
