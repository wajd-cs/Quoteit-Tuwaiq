@if ($quotes->isEmpty())
    <div class="panel p-8 text-center" data-empty>
        <p class="text-lg font-semibold text-white">{{ __('No quote fits :mood + :goal yet.', ['mood' => $mood->label(), 'goal' => $goal->label()]) }}</p>
        <p class="mt-1 text-tuwaiq-lilac">{{ __('Try another goal, or share a quote and tag it with this feeling so it helps the next person.') }}</p>
        <button type="button" popovertarget="quote-popup" class="btn-primary mt-4">{{ __('Share a quote') }}</button>
    </div>
@else
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-bold text-white">
            {{ __('For when you feel :mood and need :goal', ['mood' => mb_strtolower($mood->label()), 'goal' => mb_strtolower($goal->label())]) }}
        </h2>
        <button type="button" class="btn-ghost px-4 py-2 text-sm" data-gets-me-more>{{ __('Show me others') }}</button>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-5 md:grid-cols-3">
        @foreach ($quotes as $quote)
            <x-quote-card :quote="$quote" data-suggested-id="{{ $quote->id }}">
                <div class="space-y-3">
                    <div class="rounded-xl bg-white/5 p-3 text-xs leading-relaxed" data-why>
                        <p class="font-semibold text-tuwaiq-teal">{{ __('Why this quote?') }} · {{ \App\Support\GetsMeExplainer::label($quote) }}</p>
                        <p class="mt-1 text-tuwaiq-text">{{ \App\Support\GetsMeExplainer::explain($quote, $mood, $goal, $goalCategoryIds) }}</p>
                    </div>
                    <form method="POST" action="{{ route('gets-me.pick') }}" data-pick class="flex justify-end">
                        @csrf
                        <input type="hidden" name="quote_id" value="{{ $quote->id }}">
                        <input type="hidden" name="mood" value="{{ $mood->slug }}">
                        <input type="hidden" name="goal" value="{{ $goal->slug }}">
                        <button type="submit" class="btn-primary px-4 py-1.5 text-sm" data-label-done="{{ __('Added to history') }}">{{ __('This gets me') }}</button>
                    </form>
                </div>
            </x-quote-card>
        @endforeach
    </div>
    <p class="mt-3 text-center text-xs text-tuwaiq-lilac">{{ __('Use the bookmark to save a quote to your favourites, or “This gets me” to keep it in your history.') }}</p>
@endif
