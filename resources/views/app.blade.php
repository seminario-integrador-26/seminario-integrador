<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="claro">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#102548">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Tema antes del primer pintado (evita el destello claro con tema marino). Misma lógica que resources/js/tema.js. --}}
        <script>
            (function () {
                var tema = 'claro';
                try {
                    var preferido = localStorage.getItem('tema');
                    if (preferido === 'claro' || preferido === 'marino') {
                        tema = preferido;
                    } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        tema = 'marino';
                    }
                } catch (e) {}
                document.documentElement.setAttribute('data-theme', tema);
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-mono:400,500|ibm-plex-sans:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
