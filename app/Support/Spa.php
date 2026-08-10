<?php

namespace App\Support;

use Illuminate\Contracts\View\View;

class Spa
{
    /**
     * Render the SPA shell with a page component and props.
     *
     * @param  array<string, mixed>  $props
     */
    public static function render(string $component, array $props = []): View
    {
        return view('app', [
            'component' => $component,
            'props' => $props,
        ]);
    }
}
