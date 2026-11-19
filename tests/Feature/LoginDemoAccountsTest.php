<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginDemoAccountsTest extends TestCase
{
    public function test_demo_shortcuts_are_hidden_by_default(): void
    {
        config(['bhn.show_demo_accounts' => false]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('demoAccounts', [])
                ->where('demoPassword', null)
            );
    }

    public function test_demo_shortcuts_are_hidden_outside_the_local_environment(): void
    {
        config([
            'bhn.show_demo_accounts' => true,
            'bhn.demo_accounts' => [['email' => 'demo@bhn.test']],
            'bhn.demo_password' => 'secret',
        ]);

        // The test suite runs in the "testing" environment.
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('demoAccounts', [])
                ->where('demoPassword', null)
            );
    }

    public function test_demo_shortcuts_are_available_in_local_when_enabled(): void
    {
        $this->app['env'] = 'local';

        config([
            'bhn.show_demo_accounts' => true,
            'bhn.demo_accounts' => [['email' => 'demo@bhn.test']],
            'bhn.demo_password' => 'secret',
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('demoAccounts.0.email', 'demo@bhn.test')
                ->where('demoPassword', 'secret')
            );
    }
}
