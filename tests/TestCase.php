<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function asStaticUser(): static
    {
        return $this->withSession([
            'static_auth_user' => [
                'name' => 'Arjun Sharma',
                'email' => 'admin@nexacore.com',
                'role' => 'Administrator',
                'initials' => 'AS',
            ],
        ]);
    }
}
