<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    config([
        'stekjes.deploy_token' => 'test-deploy-token',
        'stekjes.deploy_endpoint_enabled' => false,
    ]);
    RateLimiter::clear('deploy-run:127.0.0.1');
});

test('deploy endpoint weigert zonder bearer token', function () {
    config(['stekjes.deploy_endpoint_enabled' => true]);

    $this->postJson('/deploy/run')->assertForbidden()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'forbidden');
});

test('deploy endpoint weigert met verkeerde token', function () {
    config(['stekjes.deploy_endpoint_enabled' => true]);

    $this->withToken('wrong', 'Bearer')
        ->postJson('/deploy/run')
        ->assertForbidden()
        ->assertJsonPath('error', 'forbidden');
});

test('deploy endpoint uitgeschakeld buiten staging zonder flag', function () {
    // phpunit APP_ENV=testing → niet staging, flag false
    $this->withToken('test-deploy-token', 'Bearer')
        ->postJson('/deploy/run')
        ->assertForbidden()
        ->assertJsonPath('error', 'disabled');
});

test('deploy endpoint draait migrate en caches wanneer enabled', function () {
    config(['stekjes.deploy_endpoint_enabled' => true]);

    Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true])->andReturn(0);
    Artisan::shouldReceive('call')->once()->with('db:seed', [
        '--class' => 'DeelplekkenSeeder',
        '--force' => true,
    ])->andReturn(0);
    Artisan::shouldReceive('call')->once()->with('config:clear')->andReturn(0);
    Artisan::shouldReceive('call')->once()->with('config:cache')->andReturn(0);
    Artisan::shouldReceive('call')->once()->with('route:cache')->andReturn(0);
    Artisan::shouldReceive('call')->once()->with('view:cache')->andReturn(0);

    $robotsPath = public_path('robots.txt');
    if (is_file($robotsPath)) {
        @unlink($robotsPath);
    }

    try {
        $this->withToken('test-deploy-token', 'Bearer')
            ->postJson('/deploy/run')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('password');
    } finally {
        if (is_file($robotsPath)) {
            @unlink($robotsPath);
        }
    }
});

test('deploy endpoint schrijft env-aware robots.txt naar public_path', function () {
    config([
        'stekjes.deploy_endpoint_enabled' => true,
        'site.noindex' => false,
    ]);

    Artisan::shouldReceive('call')->times(6)->andReturn(0);

    $robotsPath = public_path('robots.txt');
    if (is_file($robotsPath)) {
        @unlink($robotsPath);
    }

    try {
        $this->withToken('test-deploy-token', 'Bearer')
            ->postJson('/deploy/run')
            ->assertOk()
            ->assertJsonPath('ok', true);

        expect(is_file($robotsPath))->toBeTrue();
        $body = (string) file_get_contents($robotsPath);
        // testing-env is niet staging; noindex false → allow + sitemap
        expect($body)->toContain('Allow: /')
            ->and($body)->toContain('Sitemap:')
            ->and($body)->not->toContain('Disallow: /');
    } finally {
        if (is_file($robotsPath)) {
            @unlink($robotsPath);
        }
    }
});

test('deploy endpoint schrijft disallow robots.txt bij noindex', function () {
    config([
        'stekjes.deploy_endpoint_enabled' => true,
        'site.noindex' => true,
    ]);

    Artisan::shouldReceive('call')->times(6)->andReturn(0);

    $robotsPath = public_path('robots.txt');
    if (is_file($robotsPath)) {
        @unlink($robotsPath);
    }

    try {
        $this->withToken('test-deploy-token', 'Bearer')
            ->postJson('/deploy/run')
            ->assertOk();

        expect(file_get_contents($robotsPath))->toBe("User-agent: *\nDisallow: /\n");
    } finally {
        if (is_file($robotsPath)) {
            @unlink($robotsPath);
        }
    }
});

test('deploy endpoint actief op staging zonder extra flag', function () {
    $previous = $this->app['env'];
    $this->app['env'] = 'staging';
    $robotsPath = public_path('robots.txt');

    try {
        Artisan::shouldReceive('call')->times(6)->andReturn(0);

        // HTTPS: SecurityHeaders forceert https zodra env≠testing.
        $this->withToken('test-deploy-token', 'Bearer')
            ->postJson('https://localhost/deploy/run')
            ->assertOk()
            ->assertJsonPath('ok', true);

        expect(file_get_contents($robotsPath))->toBe("User-agent: *\nDisallow: /\n");
    } finally {
        $this->app['env'] = $previous;
        if (is_file($robotsPath)) {
            @unlink($robotsPath);
        }
    }
});

test('deploy endpoint rate limit na te veel pogingen', function () {
    config(['stekjes.deploy_endpoint_enabled' => true]);

    $key = 'deploy-run:127.0.0.1';
    RateLimiter::clear($key);
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($key, 3600);
    }

    $this->withToken('test-deploy-token', 'Bearer')
        ->postJson('/deploy/run')
        ->assertStatus(429)
        ->assertJsonPath('error', 'rate_limited');
});
