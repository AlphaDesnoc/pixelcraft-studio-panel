<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0a0a0b">
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <script>
            (function () {
                var key = 'theme_preference';
                var valid = ['light', 'dark', 'system'];
                var stored = localStorage.getItem(key);
                var pref = valid.indexOf(stored) >= 0 ? stored : 'dark';
                var dark =
                    pref === 'dark' ||
                    (pref === 'system' &&
                        window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.add(dark ? 'dark' : 'light');
            })();
        </script>

        <title inertia>{{ config('app.name', 'Pixelcraft Studios') }}</title>

        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-background font-sans text-foreground antialiased">
        @inertia
    </body>
</html>
