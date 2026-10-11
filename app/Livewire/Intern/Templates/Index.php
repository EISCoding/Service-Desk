<?php

namespace App\Livewire\Intern\Templates;

use App\Enums\TemplateType;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\MailTemplate;
use App\Support\Html;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * System templates (automatic mails) and text snippets.
 */
#[Layout('layouts::intern')]
#[Title('Vorlagen')]
class Index extends Component
{
    use InteractsWithToasts;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $subject = '';

    public string $body = '';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('viewAny', MailTemplate::class);
    }

    /**
     * @return Collection<int, MailTemplate>
     */
    #[Computed]
    public function systemTemplates(): Collection
    {
        return MailTemplate::where('type', TemplateType::System->value)->orderBy('id')->get();
    }

    /**
     * @return Collection<int, MailTemplate>
     */
    #[Computed]
    public function snippets(): Collection
    {
        return MailTemplate::snippets()->get();
    }

    #[Computed]
    public function editing(): ?MailTemplate
    {
        return $this->editingId ? MailTemplate::find($this->editingId) : null;
    }

    public function create(): void
    {
        $this->authorize('create', MailTemplate::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $templateId): void
    {
        $template = MailTemplate::findOrFail($templateId);
        $this->authorize('update', $template);

        $this->resetForm();
        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->subject = (string) $template->subject;
        $this->body = $template->body;
        $this->is_active = $template->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $template = $this->editingId ? MailTemplate::findOrFail($this->editingId) : new MailTemplate(['type' => TemplateType::Snippet]);
        $this->authorize($template->exists ? 'update' : 'create', $template->exists ? $template : MailTemplate::class);

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => [$template->isSystem() ? 'required' : 'nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:200000', function ($attribute, $value, $fail) {
                if (Html::toText(Html::sanitize($value)) === '') {
                    $fail('Bitte einen Text eingeben.');
                }
            }],
        ], attributes: ['name' => 'Name', 'subject' => 'Betreff', 'body' => 'Text']);

        $template->fill([
            'name' => trim($this->name),
            'subject' => trim($this->subject) ?: null,
            'body' => $this->body,
            'is_active' => $template->isSystem() ? $this->is_active : true,
        ])->save();

        $this->showForm = false;
        unset($this->systemTemplates, $this->snippets);
        $this->toast('Vorlage gespeichert.');
    }

    public function toggleActive(int $templateId): void
    {
        $template = MailTemplate::findOrFail($templateId);
        $this->authorize('update', $template);

        $template->update(['is_active' => ! $template->is_active]);

        unset($this->systemTemplates);
        $this->toast($template->is_active ? "„{$template->name}“ aktiviert." : "„{$template->name}“ deaktiviert.");
    }

    public function delete(int $templateId): void
    {
        $template = MailTemplate::findOrFail($templateId);
        $this->authorize('delete', $template);

        $template->delete();

        unset($this->snippets);
        $this->toast('Textbaustein gelöscht.');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'subject', 'body', 'is_active');
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.intern.templates.index');
    }
}
