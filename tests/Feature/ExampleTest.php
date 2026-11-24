<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_sends_visitors_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_the_login_page_loads(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_public_registration_does_not_exist_but_forgot_password_does(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertOk();
    }
}
