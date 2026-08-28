<x-guest-layout>
    <div class="max-w-sm">
        <p class="text-xs font-bold uppercase text-blue-900/75" style="letter-spacing: 0;">Discador THCONECT</p>
        <h1 class="mt-2 text-3xl font-semibold text-slate-900">Entrar no discador</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">Informe seu usuário e senha para acessar sua operação.</p>
    </div>

    <x-auth-session-status class="mt-6 rounded-md border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="Usuário" class="text-sm font-semibold text-slate-700" />
            <x-text-input
                id="email"
                class="mt-2 block h-12 w-full rounded-md border-slate-300 bg-blue-50/70 px-3 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600"
                type="email"
                name="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Senha" class="text-sm font-semibold text-slate-700" />
            <x-text-input
                id="password"
                class="mt-2 block h-12 w-full rounded-md border-slate-300 bg-blue-50/70 px-3 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:ring-blue-600"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <button
            type="submit"
            class="inline-flex h-12 w-full items-center justify-center rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 active:bg-blue-800"
        >
            Entrar com segurança
        </button>
    </form>
</x-guest-layout>
