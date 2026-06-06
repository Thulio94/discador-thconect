<x-guest-layout>
    <div class="mx-auto flex max-w-[312px] flex-col items-center text-center">
        <x-application-logo class="h-24 w-24" />
        <h1 class="mt-5 text-2xl font-semibold" style="color: var(--fg);">Criar acesso</h1>
    </div>

    <form method="POST" action="{{ route('register') }}" class="mt-7 space-y-5">
        @csrf

        <div>
            <x-input-label for="name" value="Nome" />
            <x-text-input id="name" class="mt-1.5 block h-11 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" class="mt-1.5 block h-11 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Senha" />
            <x-text-input id="password" class="mt-1.5 block h-11 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirmar senha" />
            <x-text-input id="password_confirmation" class="mt-1.5 block h-11 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <a class="text-sm font-medium text-blue-600 underline-offset-4 hover:underline" href="{{ route('login') }}">
                Ja tenho acesso
            </a>
            <x-ui.button type="submit">Criar acesso</x-ui.button>
        </div>
    </form>
</x-guest-layout>
