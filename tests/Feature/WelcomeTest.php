<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the welcome page renders with inertia and expected properties', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('appName')
            ->has('version')
            ->has('phpVersion')
            ->has('laravelVersion')
            ->has('environment')
        );
});
