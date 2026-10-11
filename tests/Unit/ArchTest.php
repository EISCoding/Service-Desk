<?php

use Symfony\Component\Finder\Finder;

arch('no debugging statements are left in the code')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('models extend Eloquent')
    ->expect('App\Models')
    ->classes()
    ->toExtend('Illuminate\Database\Eloquent\Model');

it('never checks role names for authorization (CLAUDE.md: permissions only)', function () {
    $files = collect(Finder::create()->files()->in([
        base_path('app'), base_path('routes'), base_path('resources/views'),
    ])->name(['*.php']));

    $offenders = $files
        ->filter(fn ($file) => preg_match('/hasRole|hasAnyRole|hasAllRoles|@role|role:/', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
