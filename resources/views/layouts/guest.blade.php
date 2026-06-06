<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="thconectclaro">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Discador THCONECT') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" style="background: var(--bg-subtle); color: var(--fg);">
        <div class="flex min-h-screen items-center justify-center px-4 py-10" style="background: var(--aurora);">
            <div class="th-card w-full max-w-[430px] px-8 py-10 shadow-2xl sm:px-11" style="border-radius: var(--radius-xl);">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
