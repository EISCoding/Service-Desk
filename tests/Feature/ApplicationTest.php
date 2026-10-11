<?php

it('boots and answers the health check', function () {
    $this->get('/up')->assertOk();
});

it('redirects the start page to the staff area', function () {
    $this->get('/')->assertRedirect('/intern');
});

it('runs Livewire with the class based component setup', function () {
    expect(config('livewire.class_namespace'))->toBe('App\\Livewire')
        ->and(config('livewire.make_command.type'))->toBe('class');
});
