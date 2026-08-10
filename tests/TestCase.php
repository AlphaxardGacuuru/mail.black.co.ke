<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Route;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessRouteExists(string $routeName, ?string $message = null): void
    {
        if (! Route::has($routeName)) {
            $this->markTestSkipped($message ?? "Route [{\$routeName}] is not defined.");
        }
    }
}
