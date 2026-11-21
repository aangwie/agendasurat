<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL should send visitors to the dashboard,
     * and guests are then redirected to the login screen.
     */
    public function test_the_root_url_redirects_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertRedirect('/login');
    }
}
