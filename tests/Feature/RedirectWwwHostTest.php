<?php

beforeEach(fn () => config(['app.url' => 'https://framework.test']));

it('redirects the www host to the app host, keeping the path and query', function () {
    $this->get('https://www.framework.test/docs/getting-started?tab=r')
        ->assertStatus(301)
        ->assertRedirect('https://framework.test/docs/getting-started?tab=r');
});

it('leaves the app host alone', function () {
    $this->get('https://framework.test/robots.txt')->assertOk();
});

it('leaves other subdomains alone', function () {
    $this->get('https://www2.framework.test/robots.txt')->assertOk();
});
