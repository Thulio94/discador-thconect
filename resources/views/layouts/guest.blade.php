<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="thconectclaro">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">

        <title>{{ config('app.name', 'Discador THCONECT') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" style="background: var(--bg-subtle); color: var(--fg);">
        @if (request()->routeIs('login'))
            <div class="min-h-screen bg-slate-100 lg:grid lg:grid-cols-[minmax(0,58%)_minmax(420px,42%)]">
                <section class="relative hidden min-h-screen overflow-hidden bg-[#123263] px-12 py-16 text-white lg:flex lg:flex-col lg:justify-between xl:px-20">
                    <div class="relative z-10">
                        <a href="/" class="inline-flex h-48 w-48 items-center justify-center bg-slate-100 p-7">
                            <x-application-logo class="h-full w-full" />
                        </a>
                    </div>

                    <div class="relative z-10 max-w-xl pb-4">
                        <p class="text-xs font-bold uppercase text-cyan-300" style="letter-spacing: 0;">Discador THCONECT</p>
                        <h1 class="mt-4 text-5xl font-semibold leading-tight xl:text-6xl">Uma operação de discagem no navegador.</h1>
                        <p class="mt-6 max-w-md text-lg leading-7 text-blue-100">
                            Acesse sua operação, atenda contatos e acompanhe cada chamada em um único lugar.
                        </p>
                    </div>

                    <div aria-hidden="true" class="absolute -bottom-72 -right-72 h-[680px] w-[680px] rounded-full border border-blue-400/40"></div>
                    <div aria-hidden="true" class="absolute -bottom-56 -right-56 h-[560px] w-[560px] rounded-full border border-blue-300/20"></div>
                </section>

                <main class="flex min-h-screen items-center justify-center bg-slate-100 px-5 py-10 sm:px-10">
                    <div class="w-full max-w-[480px] rounded-lg border border-slate-200 bg-white px-7 py-9 shadow-xl sm:px-11 sm:py-11">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        @else
            <div class="flex min-h-screen items-center justify-center px-4 py-10" style="background: var(--aurora);">
                <div class="th-card w-full max-w-[430px] px-8 py-10 shadow-2xl sm:px-11" style="border-radius: var(--radius-xl);">
                    {{ $slot }}
                </div>
            </div>
        @endif
    </body>
</html>
