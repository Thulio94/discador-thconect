<x-guest-layout>
    <div class="mx-auto flex max-w-[312px] flex-col items-center text-center">
        <x-application-logo class="h-24 w-24" />
        <h1 class="mt-5 text-2xl font-semibold" style="color: var(--fg);">Recuperar senha</h1>
        <p class="mt-2 text-sm leading-5" style="color: var(--fg-muted);">
            Informe seu e-mail para receber o link de redefinicao.
        </p>
    </div>

    <x-auth-session-status class="mt-6 rounded-md border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" class="mt-1.5 block h-11 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-ui.button type="submit" class="w-full">Enviar link</x-ui.button>
    </form>
</x-guest-layout>
