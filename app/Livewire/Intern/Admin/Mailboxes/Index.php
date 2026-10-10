<?php

namespace App\Livewire\Intern\Admin\Mailboxes;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Mail\Mailboxes\ConnectionTester;
use App\Models\Group;
use App\Models\Mailbox;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::intern')]
#[Title('Postfächer')]
class Index extends Component
{
    use InteractsWithToasts;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $imap_host = '';

    public int $imap_port = 993;

    public string $imap_encryption = 'ssl';

    public string $imap_username = '';

    public string $imap_password = '';

    public string $imap_folder = 'INBOX';

    public string $processed_action = 'mark_read';

    public string $processed_folder = '';

    public string $smtp_host = '';

    public int $smtp_port = 587;

    public string $smtp_encryption = 'tls';

    public string $smtp_username = '';

    public string $smtp_password = '';

    public string $default_group_id = '';

    public bool $is_active = true;

    public bool $is_default = false;

    /** Result of the last connection test: ['imap' => ['ok' => bool, 'message' => string], …] */
    public array $testResults = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Mailbox::class);
    }

    /**
     * @return Collection<int, Mailbox>
     */
    #[Computed]
    public function mailboxes(): Collection
    {
        return Mailbox::with('defaultGroup:id,name')->withCount('tickets')->orderBy('name')->get();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function groupOptions(): array
    {
        return Group::orderBy('name')->pluck('name', 'id')->all();
    }

    public function create(): void
    {
        $this->authorize('create', Mailbox::class);

        $this->resetForm();
        $this->is_default = Mailbox::count() === 0;
        $this->showForm = true;
    }

    public function edit(int $mailboxId): void
    {
        $mailbox = Mailbox::findOrFail($mailboxId);
        $this->authorize('update', $mailbox);

        $this->resetForm();
        $this->editingId = $mailbox->id;
        $this->fill($mailbox->only([
            'name', 'email', 'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_folder',
            'processed_action', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'is_active', 'is_default',
        ]));
        $this->processed_folder = (string) $mailbox->processed_folder;
        $this->default_group_id = (string) $mailbox->default_group_id;
        $this->showForm = true;
    }

    /**
     * Fill in the usual ports when the encryption changes.
     */
    public function updatedImapEncryption(string $value): void
    {
        $this->imap_port = $value === 'ssl' ? 993 : 143;
    }

    public function updatedSmtpEncryption(string $value): void
    {
        $this->smtp_port = match ($value) {
            'ssl' => 465,
            'tls' => 587,
            default => 25,
        };
    }

    /**
     * Use the address as user names unless already filled.
     */
    public function updatedEmail(string $value): void
    {
        $this->imap_username = $this->imap_username ?: $value;
        $this->smtp_username = $this->smtp_username ?: $value;
    }

    public function testImap(ConnectionTester $tester): void
    {
        $this->authorizeForm();
        $this->validate(array_intersect_key($this->rules(), array_flip(['imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_folder'])));

        $error = $tester->imap([
            'host' => $this->imap_host, 'port' => $this->imap_port, 'encryption' => $this->imap_encryption,
            'username' => $this->imap_username, 'password' => $this->password('imap'), 'folder' => $this->imap_folder,
        ]);

        $this->testResults['imap'] = ['ok' => $error === null, 'message' => $error ?? 'IMAP-Verbindung erfolgreich.'];
    }

    public function testSmtp(ConnectionTester $tester): void
    {
        $this->authorizeForm();
        $this->validate(array_intersect_key($this->rules(), array_flip(['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username'])));

        $error = $tester->smtp([
            'host' => $this->smtp_host, 'port' => $this->smtp_port, 'encryption' => $this->smtp_encryption,
            'username' => $this->smtp_username, 'password' => $this->password('smtp'),
        ]);

        $this->testResults['smtp'] = ['ok' => $error === null, 'message' => $error ?? 'SMTP-Verbindung erfolgreich.'];
    }

    public function save(): void
    {
        $mailbox = $this->editingId ? Mailbox::findOrFail($this->editingId) : new Mailbox;
        $creating = ! $mailbox->exists;
        $this->authorize($creating ? 'create' : 'update', $creating ? Mailbox::class : $mailbox);

        $this->email = Str::lower(trim($this->email));
        $validated = $this->validate($this->rules($mailbox));

        $data = collect($validated)->except(['imap_password', 'smtp_password', 'default_group_id'])->all();
        $data['processed_folder'] = $this->processed_action === 'move' ? $this->processed_folder : null;

        $mailbox->fill($data);
        foreach (['imap_password', 'smtp_password'] as $field) {
            if ($this->{$field} !== '') {
                $mailbox->{$field} = $this->{$field};
            }
        }
        $mailbox->defaultGroup()->associate($this->default_group_id ?: null);
        $mailbox->save();

        $this->showForm = false;
        unset($this->mailboxes);
        $this->toast($creating ? 'Postfach angelegt.' : 'Postfach gespeichert.');
    }

    public function delete(int $mailboxId): void
    {
        $mailbox = Mailbox::findOrFail($mailboxId);
        $this->authorize('delete', $mailbox);

        $mailbox->delete();

        unset($this->mailboxes);
        $this->toast('Postfach gelöscht.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(?Mailbox $mailbox = null): array
    {
        $mailbox ??= $this->editingId ? Mailbox::find($this->editingId) : null;
        $passwordRule = $mailbox?->exists ? 'nullable' : 'required';
        $encryptions = array_keys(Mailbox::ENCRYPTIONS);

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('mailboxes', 'email')->ignore($mailbox?->id)],
            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'between:1,65535'],
            'imap_encryption' => ['required', Rule::in($encryptions)],
            'imap_username' => ['required', 'string', 'max:255'],
            'imap_password' => [$passwordRule, 'string', 'max:500'],
            'imap_folder' => ['required', 'string', 'max:100'],
            'processed_action' => ['required', Rule::in(['mark_read', 'move'])],
            'processed_folder' => ['nullable', 'required_if:processed_action,move', 'string', 'max:100'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', Rule::in($encryptions)],
            'smtp_username' => ['required', 'string', 'max:255'],
            'smtp_password' => [$passwordRule, 'string', 'max:500'],
            'default_group_id' => ['nullable', Rule::exists('groups', 'id')],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'Name', 'email' => 'E-Mail-Adresse', 'imap_host' => 'IMAP-Server', 'imap_port' => 'IMAP-Port',
            'imap_encryption' => 'IMAP-Verschlüsselung', 'imap_username' => 'IMAP-Benutzer', 'imap_password' => 'IMAP-Passwort',
            'imap_folder' => 'Ordner', 'processed_action' => 'Verarbeitete Mails', 'processed_folder' => 'Zielordner',
            'smtp_host' => 'SMTP-Server', 'smtp_port' => 'SMTP-Port', 'smtp_encryption' => 'SMTP-Verschlüsselung',
            'smtp_username' => 'SMTP-Benutzer', 'smtp_password' => 'SMTP-Passwort', 'default_group_id' => 'Standardgruppe',
        ];
    }

    /**
     * The entered password, or the stored one when editing with an empty field.
     */
    private function password(string $protocol): string
    {
        $field = "{$protocol}_password";

        return $this->{$field} !== '' ? $this->{$field} : (string) Mailbox::find($this->editingId)?->{$field};
    }

    private function authorizeForm(): void
    {
        $this->editingId
            ? $this->authorize('update', Mailbox::findOrFail($this->editingId))
            : $this->authorize('create', Mailbox::class);
    }

    private function resetForm(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.intern.admin.mailboxes.index', [
            'encryptions' => Mailbox::ENCRYPTIONS,
        ]);
    }
}
