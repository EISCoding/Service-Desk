<?php

use App\Models\Customer;
use App\Services\CustomerNumberGenerator;
use Illuminate\Database\UniqueConstraintViolationException;

it('assigns K-10001 to the first customer', function () {
    expect(Customer::factory()->create()->number)->toBe('K-10001');
});

it('counts up from the highest existing number', function () {
    Customer::factory()->create(['number' => 'K-10041']);
    Customer::factory()->create(['number' => 'K-10007']);

    expect(Customer::factory()->create()->number)->toBe('K-10042');
});

it('keeps a manually entered number and ignores foreign formats for the counter', function () {
    $manual = Customer::factory()->create(['number' => 'ALT-4711']);
    Customer::factory()->create(['number' => 'K-ABC']);

    expect($manual->number)->toBe('ALT-4711')
        ->and(app(CustomerNumberGenerator::class)->next())->toBe('K-10001');
});

it('never goes below the configured start', function () {
    Customer::factory()->create(['number' => 'K-5']);

    expect(app(CustomerNumberGenerator::class)->next())->toBe('K-10001');
});

it('requires unique customer numbers', function () {
    Customer::factory()->create(['number' => 'K-10001']);

    Customer::factory()->create(['number' => 'K-10001']);
})->throws(UniqueConstraintViolationException::class);

it('defaults to Germany and formats the address', function () {
    $customer = Customer::factory()->create(['street' => 'Hauptstraße 1', 'postal_code' => '10115', 'city' => 'Berlin']);

    expect($customer->country)->toBe('DE')
        ->and($customer->addressLines())->toBe(['Hauptstraße 1', '10115 Berlin']);

    $customer->country = 'AT';
    expect($customer->addressLines())->toBe(['Hauptstraße 1', '10115 Berlin', 'Österreich']);
});

it('separates active and archived customers', function () {
    Customer::factory()->count(2)->create();
    Customer::factory()->archived()->create();

    expect(Customer::active()->count())->toBe(2)
        ->and(Customer::archived()->count())->toBe(1)
        ->and(Customer::archived()->first()->isArchived())->toBeTrue();
});

it('logs changes to customers', function () {
    $customer = Customer::factory()->create(['company' => 'Alt GmbH']);
    $customer->update(['company' => 'Neu GmbH']);

    expect($customer->activities->pluck('action')->all())->toBe(['updated', 'created'])
        ->and($customer->activities->first()->changes)->toBe(['company' => ['old' => 'Alt GmbH', 'new' => 'Neu GmbH']]);
});
