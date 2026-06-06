<x-guest-layout>
    <div class="mx-auto flex max-w-[312px] flex-col items-center text-center">
        <x-application-logo class="h-24 w-24" />
        <h1 class="mt-5 text-2xl font-semibold" style="color: var(--fg);">Confirmar acesso</h1>
        <p class="mt-2 text-sm leading-5" style="color: var(--fg-muted);">Digite sua senha para continuar.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-7 space-y-5">
        @csrf

        <div>
            <x-input-label for="password" value="Senha" />
            <x-text-input id="password" class="mt-1.5 block h-11 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <x-ui.button type="submit" class="w-full">Confirmar</x-ui.button>
    </form>
</x-guest-layout>
