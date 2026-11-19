<?php

it('keeps sign-in pages out of search results', function () {
    $this->get('/login')->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
});

it('keeps the guide search index out of search results', function () {
    $this->get('/docs/search.json')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
});
