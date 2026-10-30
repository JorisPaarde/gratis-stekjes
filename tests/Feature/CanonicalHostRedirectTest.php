<?php

declare(strict_types=1);

test('gratisstekjes.nl redirect naar canonieke host buiten staging', function () {
    $previous = $this->app['env'];
    $this->app['env'] = 'production';
    config(['site.canonical_host' => 'gratisplanten.nl']);

    try {
        // HTTPS vermijdt SecurityHeaders-http→https 301 die ook triggert als env≠testing.
        $this->get('https://gratisstekjes.nl/aanbod?q=munt')
            ->assertRedirect('https://gratisplanten.nl/aanbod?q=munt')
            ->assertStatus(301);
    } finally {
        $this->app['env'] = $previous;
    }
});

test('www.gratisstekjes.nl redirect behoudt pad', function () {
    $previous = $this->app['env'];
    $this->app['env'] = 'production';
    config(['site.canonical_host' => 'gratisplanten.nl']);

    try {
        $this->get('https://www.gratisstekjes.nl/stektips/tomaten')
            ->assertRedirect('https://gratisplanten.nl/stektips/tomaten')
            ->assertStatus(301);
    } finally {
        $this->app['env'] = $previous;
    }
});

test('geen alias-redirect op staging', function () {
    $previous = $this->app['env'];
    $this->app['env'] = 'staging';

    try {
        // robots.txt: geen Vite-manifest nodig.
        $this->get('https://gratisstekjes.nl/robots.txt')
            ->assertOk()
            ->assertHeaderMissing('Location');
    } finally {
        $this->app['env'] = $previous;
    }
});

test('geen alias-redirect op local/testing', function () {
    $this->get('http://gratisstekjes.nl/robots.txt')
        ->assertOk()
        ->assertHeaderMissing('Location');
});

test('canonieke host zelf wordt niet geredirect', function () {
    $previous = $this->app['env'];
    $this->app['env'] = 'production';

    try {
        $this->get('https://gratisplanten.nl/robots.txt')
            ->assertOk()
            ->assertHeaderMissing('Location');
    } finally {
        $this->app['env'] = $previous;
    }
});
