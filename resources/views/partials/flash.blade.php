@if (session('status'))
    <div class="mx-auto mt-4 max-w-6xl px-4">
        <div class="rounded-2xl border border-tuwaiq-teal/40 bg-tuwaiq-teal/15 px-4 py-3 text-sm text-white" role="status">
            {{ __(session('status')) }}
        </div>
    </div>
@endif
