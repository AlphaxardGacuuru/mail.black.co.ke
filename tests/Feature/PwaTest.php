<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_home_page_includes_pwa_metadata(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('rel="manifest" href="/manifest.webmanifest"', false);
        $response->assertSee('name="theme-color" content="#171717"', false);
        $response->assertSee('name="mobile-web-app-capable" content="yes"', false);
    }

    public function test_pwa_files_exist(): void
    {
        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('android-chrome-192x192.png'));
        $this->assertFileExists(public_path('android-chrome-512x512.png'));
    }
}
