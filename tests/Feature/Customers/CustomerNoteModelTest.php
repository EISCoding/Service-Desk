<?php

use App\Models\Activity;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;

it('belongs to a customer and an author', function () {
    $note = CustomerNote::factory()->create();

    expect($note->customer)->toBeInstanceOf(Customer::class)
        ->and($note->author)->toBeInstanceOf(User::class)
        ->and($note->is_pinned)->toBeFalse();
});

it('lists pinned notes first, then the newest', function () {
    $customer = Customer::factory()->create();
    CustomerNote::factory()->for($customer)->create(['body' => 'alt', 'created_at' => now()->subDays(2)]);
    CustomerNote::factory()->for($customer)->create(['body' => 'neu', 'created_at' => now()]);
    CustomerNote::factory()->for($customer)->pinned()->create(['body' => 'angeheftet', 'created_at' => now()->subWeek()]);

    expect($customer->notes->pluck('body')->all())->toBe(['angeheftet', 'neu', 'alt']);
});

it('keeps the note when its author is removed', function () {
    $note = CustomerNote::factory()->create();

    $note->author->delete();

    expect($note->fresh())->not->toBeNull()->user_id->toBeNull();
});

it('is deleted with its customer', function () {
    $note = CustomerNote::factory()->create();

    $note->customer->delete();

    expect(CustomerNote::find($note->id))->toBeNull();
});

it('logs changes into the history of its customer', function () {
    $note = CustomerNote::factory()->create();
    $note->update(['is_pinned' => true]);

    expect(Activity::forHistoryOf($note->customer)->where('subject_type', 'customer_note')->pluck('action')->all())
        ->toEqualCanonicalizing(['created', 'updated']);
});
