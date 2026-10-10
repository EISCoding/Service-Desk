<?php

/*
 * Policies for every customer action: customers.manage for customers,
 * contacts and notes; contacts.portal for portal access; authors for their notes.
 */

use App\Livewire\Intern\Customers\Contacts;
use App\Livewire\Intern\Customers\CustomerForm;
use App\Livewire\Intern\Customers\History;
use App\Livewire\Intern\Customers\Notes;
use App\Livewire\Intern\Customers\Show;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->customer = Customer::factory()->create();
    $this->contact = Contact::factory()->for($this->customer)->withPortalAccess()->create();
    $this->note = CustomerNote::factory()->for($this->customer)->create();
});

it('keeps users without customers.manage out of every page and action', function (string $component, string $method, array $args) {
    $this->actingAs(User::factory()->create()); // no permissions

    $params = $component === CustomerForm::class ? [] : ['customer' => $this->customer];
    $args = array_map(fn ($arg) => $arg === ':contact' ? $this->contact->id : ($arg === ':note' ? $this->note->id : ($arg === ':customer' ? $this->customer->id : $arg)), $args);

    $test = Livewire::test($component, $params);
    if ($method !== 'mount') {
        $test->call($method, ...$args);
    }
    $test->assertForbidden();
})->with([
    'customer page' => [Show::class, 'mount', []],
    'contacts tab' => [Contacts::class, 'mount', []],
    'notes tab' => [Notes::class, 'mount', []],
    'history tab' => [History::class, 'mount', []],
    'create customer' => [CustomerForm::class, 'create', []],
    'edit customer' => [CustomerForm::class, 'edit', [':customer']],
]);

it('lets staff manage everything except other people\'s notes', function () {
    $staff = User::factory()->staff()->create();

    expect($staff->can('viewAny', Customer::class))->toBeTrue()
        ->and($staff->can('create', Customer::class))->toBeTrue()
        ->and($staff->can('update', $this->customer))->toBeTrue()
        ->and($staff->can('archive', $this->customer))->toBeTrue()
        ->and($staff->can('delete', $this->customer))->toBeTrue()
        ->and($staff->can('create', Contact::class))->toBeTrue()
        ->and($staff->can('update', $this->contact))->toBeTrue()
        ->and($staff->can('delete', $this->contact))->toBeTrue()
        ->and($staff->can('managePortal', $this->contact))->toBeTrue()
        ->and($staff->can('create', CustomerNote::class))->toBeTrue()
        ->and($staff->can('pin', $this->note))->toBeTrue()
        ->and($staff->can('update', $this->note))->toBeFalse()
        ->and($staff->can('delete', $this->note))->toBeFalse()
        ->and($this->note->author->can('update', $this->note))->toBeFalse(); // author has no customers.manage
});

it('separates portal management from customer management', function () {
    $portalOnly = User::factory()->create();
    $portalOnly->givePermissionTo('contacts.portal');

    expect($portalOnly->can('managePortal', $this->contact))->toBeTrue()
        ->and($portalOnly->can('update', $this->contact))->toBeFalse()
        ->and($portalOnly->can('viewAny', Customer::class))->toBeFalse();
});

it('rejects tampering with locked properties from the browser', function (string $component, string $property, mixed $value) {
    $this->actingAs(User::factory()->staff()->create());

    $params = $component === CustomerForm::class ? [] : ['customer' => $this->customer];

    Livewire::test($component, $params)->set($property, $value);
})->with([
    'customer of a tab' => [Contacts::class, 'customer', 999],
    'edited contact' => [Contacts::class, 'editingId', 999],
    'edited note' => [Notes::class, 'editingId', 999],
    'edited customer' => [CustomerForm::class, 'customerId', 999],
])->throws(CannotUpdateLockedPropertyException::class);

it('logs every change to customers, contacts and notes', function () {
    $staff = User::factory()->staff()->create();
    $this->actingAs($staff);

    Livewire::test(CustomerForm::class)->dispatch('edit-customer', id: $this->customer->id)->set('city', 'Bonn')->call('save');
    Livewire::test(Contacts::class, ['customer' => $this->customer])->call('edit', $this->contact->id)->set('position', 'CEO')->call('save');
    Livewire::test(Contacts::class, ['customer' => $this->customer])->call('invite', $this->contact->id);
    Livewire::test(Notes::class, ['customer' => $this->customer])->set('body', 'Neu')->call('add');
    Livewire::test(Notes::class, ['customer' => $this->customer])->call('togglePin', $this->note->id);
    Livewire::test(Show::class, ['customer' => $this->customer])->call('archive');

    $actions = Activity::forHistoryOf($this->customer)
        ->where('causer_id', $staff->id)
        ->orderBy('id')
        ->get()
        ->map(fn ($activity) => "{$activity->subject_type}.{$activity->action}")
        ->all();

    expect($actions)->toBe([
        'customer.updated',
        'contact.updated',
        'contact.portal_invited',
        'customer_note.created',
        'customer_note.updated',
        'customer.updated',
    ]);
});
