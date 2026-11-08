<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Route;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Jobs that name their own connection (headless browser and long
        // AI requests) run inline like the rest of the queue in tests.
        config(['queue.connections.redis-browser' => ['driver' => 'sync']]);
    }

    protected function skipUnlessRouteExists(string $routeName, ?string $message = null): void
    {
        if (! Route::has($routeName)) {
            $this->markTestSkipped($message ?? "Route [{\$routeName}] is not defined.");
        }
    }
}
