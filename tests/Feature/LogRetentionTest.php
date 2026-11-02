<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Logs go to one file per day, and files older than LOG_DAILY_DAYS (14 by
 * default) are deleted by Laravel, so storage/logs never grows unbounded.
 */
class LogRetentionTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['LOG_STACK'], $_SERVER['LOG_STACK'], $_ENV['LOG_DAILY_DAYS'], $_SERVER['LOG_DAILY_DAYS']);

        parent::tearDown();
    }

    private function config(): array
    {
        return require config_path('logging.php');
    }

    public function test_logs_rotate_daily_and_keep_14_days_by_default(): void
    {
        unset($_ENV['LOG_STACK'], $_SERVER['LOG_STACK'], $_ENV['LOG_DAILY_DAYS'], $_SERVER['LOG_DAILY_DAYS']);
        putenv('LOG_STACK');
        putenv('LOG_DAILY_DAYS');

        $this->assertSame(['daily'], $this->config()['channels']['stack']['channels']);
        $this->assertSame(14, $this->config()['channels']['daily']['days']);
    }
}
