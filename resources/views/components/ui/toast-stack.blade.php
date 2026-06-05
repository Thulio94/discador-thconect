<div
    x-data="toastStack()"
    x-on:toast.window="push($event.detail)"
    class="fixed right-4 top-4 z-[90] grid w-[min(360px,calc(100vw-2rem))] gap-3"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            class="rounded-xl border p-4 text-sm shadow-lg"
            style="background: var(--surface); border-color: var(--border); color: var(--fg);"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-semibold" x-text="toast.title"></p>
                    <p class="mt-1 text-xs" style="color: var(--fg-muted);" x-text="toast.message"></p>
                </div>
                <button
                    type="button"
                    class="th-focus h-7 w-7 rounded-lg border"
                    style="border-color: var(--border);"
                    x-on:click="remove(toast.id)"
                    aria-label="Fechar"
                >
                    x
                </button>
            </div>
        </div>
    </template>
</div>
