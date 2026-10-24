@if ($errors->any())
    <div class="mb-4 rounded-2xl border border-red-400/40 bg-red-500/15 p-3 text-sm text-red-100" role="alert">
        <ul class="list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
