<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_login_page_is_accessible_to_guests(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_guests_are_redirected_away_from_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
