<x-guest-layout>
    <div class="mx-auto flex max-w-[312px] flex-col items-center text-center">
        <x-application-logo class="h-24 w-24" />
        <h1 class="mt-5 text-2xl font-semibold" style="color: var(--fg);">Verificar e-mail</h1>
        <p class="mt-2 text-sm leading-5" style="color: var(--fg-muted);">
            Enviamos um link para confirmar seu endereco de e-mail.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-6 rounded-md border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">
            Novo link enviado para seu e-mail.
        </div>
    @endif

    <div class="mt-7 flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button type="submit">Reenviar link</x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm font-medium text-blue-600 underline-offset-4 hover:underline">
                Sair
            </button>
        </form>
    </div>
</x-guest-layout>
