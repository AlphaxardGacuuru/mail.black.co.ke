<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="application-name" content="{{ config('app.name', 'Money Black') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Money Black') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#171717">
    <meta name="description"
        content="Track accounts, categories, transactions, and budget totals from one installable app.">

    {{-- Inline script to detect system dark mode preference and apply it immediately --}}
    <script>
        (function() {
            const appearance = '{{ $appearance ?? 'system' }}';

            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

    {{-- Inline style to set the HTML background color based on our theme in app.css --}}
    <style>
        html {
            background-color: oklch(1 0 0);
        }

        html.dark {
            background-color: oklch(0.145 0 0);
        }
    </style>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])

    @php
        $user = request()->user();
        $errorBag = session('errors');
        $validationErrors = $errorBag?->getBag('default')->getMessages() ?? [];
        $flattenedErrors = [];

        foreach ($validationErrors as $field => $messages) {
            $flattenedErrors[$field] = $messages[0] ?? 'Invalid value.';
        }

        $sharedProps = [
            'name' => config('app.name'),
            'auth' => [
                'user' => $user
                    ? [
                        ...$user->toArray(),
                        'avatar' => $user->avatar,
                        // 'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                    ]
                    : null,
            ],
            'sidebarOpen' => !request()->hasCookie('sidebar_state') || request()->cookie('sidebar_state') === 'true',
            'status' => session('status'),
            'flash' => [
                'toast' => session('flash.toast'),
            ],
            'errors' => $flattenedErrors,
        ];

        $spaPage = [
            'component' => $component ?? 'welcome',
            'props' => array_merge($sharedProps, $props ?? []),
            'url' => request()->getRequestUri(),
        ];
    @endphp

    <script>
        window.__SPA_PAGE__ = @json($spaPage);
    </script>

    <title>{{ config('app.name', 'Laravel') }}</title>
</head>

<body class="font-sans antialiased">
    <div id="app"></div>
</body>

</html>
