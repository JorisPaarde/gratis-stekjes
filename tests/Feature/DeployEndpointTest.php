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

    $this->withToken('test-deploy-token', 'Bearer')
        ->postJson('/deploy/run')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonMissingPath('token')
        ->assertJsonMissingPath('password');
});

test('deploy endpoint actief op staging zonder extra flag', function () {
    $previous = $this->app['env'];
    $this->app['env'] = 'staging';

    try {
        Artisan::shouldReceive('call')->times(6)->andReturn(0);

        // HTTPS: SecurityHeaders forceert https zodra env≠testing.
        $this->withToken('test-deploy-token', 'Bearer')
            ->postJson('https://localhost/deploy/run')
            ->assertOk()
            ->assertJsonPath('ok', true);
    } finally {
        $this->app['env'] = $previous;
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
