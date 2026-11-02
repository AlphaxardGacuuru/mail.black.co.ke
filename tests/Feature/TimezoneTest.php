<?php

namespace Tests\Feature;

use Tests\TestCase;

class TimezoneTest extends TestCase
{
    public function test_the_app_runs_on_nairobi_time(): void
    {
        $this->assertSame('Africa/Nairobi', config('app.timezone'));
        $this->assertSame('Africa/Nairobi', date_default_timezone_get());
        $this->assertSame('+03:00', now()->format('P'));
    }
}
