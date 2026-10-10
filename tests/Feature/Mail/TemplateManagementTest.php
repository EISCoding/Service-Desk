<?php

use App\Enums\TemplateType;
use App\Livewire\Intern\Templates\Index;
use App\Models\MailTemplate;
use App\Models\User;
use Livewire\Livewire;

it('lists system templates and snippets under /intern/vorlagen', function () {
    MailTemplate::factory()->system('ticket_opened')->create(['name' => 'Ticket eröffnet']);
    MailTemplate::factory()->create(['name' => 'Fernwartung', 'body' => '<div>Bitte TeamViewer starten.</div>']);

    $this->actingAs(User::factory()->staff()->create())
        ->get('/intern/vorlagen')
        ->assertOk()
        ->assertSee('<title>Vorlagen · ServiceDesk</title>', false)
        ->assertSeeInOrder(['Systemvorlagen', 'Ticket eröffnet', 'Textbausteine', 'Fernwartung', 'Bitte TeamViewer starten.']);
});

it('is read-only for staff without templates.manage', function () {
    $snippet = MailTemplate::factory()->create();
    $this->actingAs(User::factory()->staff()->create());

    Livewire::test(Index::class)
        ->assertDontSee('Neuer Textbaustein')
        ->call('create')->assertForbidden();
    Livewire::test(Index::class)->call('edit', $snippet->id)->assertForbidden();
    Livewire::test(Index::class)->call('delete', $snippet->id)->assertForbidden();
});

it('needs ticket access or templates.manage', function () {
    $this->actingAs(User::factory()->create())->get('/intern/vorlagen')->assertForbidden();
});

describe('with templates.manage', function () {
    beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

    it('creates, edits and deletes snippets', function () {
        Livewire::test(Index::class)
            ->call('create')
            ->set('name', 'Fernwartung')
            ->set('body', '<div>Bitte <strong>TeamViewer</strong> starten.<script>x</script></div>')
            ->call('save')
            ->assertHasNoErrors();

        $snippet = MailTemplate::sole();
        expect($snippet->type)->toBe(TemplateType::Snippet)
            ->and($snippet->body)->toBe('<div>Bitte <strong>TeamViewer</strong> starten.</div>');

        Livewire::test(Index::class)->call('edit', $snippet->id)->set('name', 'Fernwartung (Windows)')->call('save');
        expect($snippet->refresh()->name)->toBe('Fernwartung (Windows)');

        Livewire::test(Index::class)->call('delete', $snippet->id);
        expect(MailTemplate::count())->toBe(0);
    });

    it('edits system templates and switches them on and off, but never deletes them', function () {
        $template = MailTemplate::factory()->system('ticket_closed')->create(['name' => 'Ticket geschlossen']);

        Livewire::test(Index::class)
            ->call('edit', $template->id)
            ->set('subject', '')
            ->call('save')
            ->assertHasErrors('subject')
            ->set('subject', 'Ihr Ticket wurde geschlossen')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(Index::class)->call('toggleActive', $template->id);
        expect($template->refresh())->subject->toBe('Ihr Ticket wurde geschlossen')->is_active->toBeFalse()
            ->and(MailTemplate::system('ticket_closed'))->toBeNull();

        Livewire::test(Index::class)->call('delete', $template->id)->assertForbidden();
    });

    it('requires a name and text', function () {
        Livewire::test(Index::class)->call('create')->set('body', '<div><br></div>')->call('save')->assertHasErrors(['name', 'body']);
    });
});
