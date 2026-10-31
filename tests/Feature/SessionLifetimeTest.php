<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sessions last as long as Sanctum API tokens: both read SANCTUM_EXPIRATION,
 * and while tokens never expire, sessions last 400 days (the longest a
 * browser keeps a cookie).
 */
class SessionLifetimeTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['SANCTUM_EXPIRATION'], $_SERVER['SANCTUM_EXPIRATION']);

        parent::tearDown();
    }

    private function config(string $file): array
    {
        return require config_path("{$file}.php");
    }

    public function test_sessions_last_400_days_while_tokens_never_expire(): void
    {
        $this->assertNull($this->config('sanctum')['expiration']);
        $this->assertSame(60 * 24 * 400, $this->config('session')['lifetime']);
    }

    public function test_sessions_expire_with_tokens(): void
    {
        $_ENV['SANCTUM_EXPIRATION'] = $_SERVER['SANCTUM_EXPIRATION'] = '10080';

        $this->assertSame(10080, $this->config('sanctum')['expiration']);
        $this->assertSame(10080, $this->config('session')['lifetime']);
    }
}
