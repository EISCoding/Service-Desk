<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every feature test starts with all permissions and the default roles.
     */
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not depend on a frontend build (public/build).
        $this->withoutVite();
    }
}
