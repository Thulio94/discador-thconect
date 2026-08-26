<x-guest-layout>
    <div class="mx-auto flex max-w-[312px] flex-col items-center text-center">
        <a href="/" class="inline-flex items-center justify-center">
            <x-application-logo class="h-28 w-28" />
        </a>

        <h1 class="mt-5 text-2xl font-semibold" style="color: var(--fg);">
            Discador THCONECT
        </h1>

    </div>

    <x-auth-session-status class="mt-6 rounded-md border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="E-mail" class="text-xs font-medium" style="color: var(--fg);" />
            <x-text-input
                id="email"
                class="mt-1.5 block h-11 w-full rounded-md border-slate-300 bg-blue-50/70 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                style="color: var(--fg);"
                type="email"
                name="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="email"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Senha" class="text-xs font-medium" style="color: var(--fg);" />
            <x-text-input
                id="password"
                class="mt-1.5 block h-11 w-full rounded-md border-slate-300 bg-blue-50/70 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                style="color: var(--fg);"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <button
            type="submit"
            class="inline-flex h-11 w-full items-center justify-center rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 active:bg-blue-800"
        >
            Entrar
        </button>
    </form>
</x-guest-layout>
