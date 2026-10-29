@props(['groups'])

{{-- Stories: short quotes that disappear after 12 or 24 hours --}}
@php
    $payload = $groups->map(fn ($g) => [
        'name' => $g['user']->name,
        'profile' => route('users.show', $g['user']),
        'mine' => $g['mine'],
        'stories' => $g['stories'],
    ])->values();
@endphp

@if ($groups->isNotEmpty() || auth()->check())
    <section class="no-scrollbar -mx-4 mb-6 flex gap-4 overflow-x-auto px-4 pb-1" aria-label="{{ __('Stories') }}" data-tour="stories">
        @auth
            <button type="button" popovertarget="story-popup" class="story-ring-wrap" aria-label="{{ __('Add a story') }}">
                <span class="story-ring is-new"><x-icon name="plus" class="h-7 w-7" /></span>
                <span class="story-name">{{ __('Your story') }}</span>
            </button>
        @endauth
        @foreach ($groups as $index => $group)
            <button type="button" class="story-ring-wrap" data-story-open="{{ $index }}" aria-label="{{ __('Open :name’s story', ['name' => $group['user']->name]) }}">
                <span class="story-ring"><x-avatar :user="$group['user']" /></span>
                <span class="story-name" dir="auto">{{ $group['mine'] ? __('You') : \Illuminate\Support\Str::before($group['user']->name, ' ') }}</span>
            </button>
        @endforeach
    </section>

    <script type="application/json" data-story-data>@json($payload)</script>

    {{-- viewer --}}
    <div class="story-viewer" data-story-viewer hidden role="dialog" aria-modal="true" aria-label="{{ __('Stories') }}">
        <div class="story-card" data-story-card>
            <div class="flex gap-1" data-story-progress></div>
            <div class="mt-3 flex items-center gap-3">
                <a class="flex min-w-0 items-center gap-2" data-story-profile>
                    <span class="story-avatar" data-story-avatar></span>
                    <span class="min-w-0"><b class="block truncate text-[#fff]" data-story-name></b><span class="block text-xs text-[#fff]/70" data-story-ago></span></span>
                </a>
                <button type="button" class="story-btn ms-auto" data-story-pause aria-label="{{ __('Pause') }}"><x-icon name="pause" /></button>
                <button type="button" class="story-btn" data-story-close aria-label="{{ __('Close') }}"><x-icon name="x" /></button>
            </div>

            <div class="relative flex flex-1 items-center justify-center px-2 py-6">
                <button type="button" class="story-tap start-0" data-story-prev aria-label="{{ __('Previous') }}"></button>
                <button type="button" class="story-tap end-0" data-story-next aria-label="{{ __('Next') }}"></button>
                <figure class="pointer-events-none text-center">
                    <blockquote class="quote-text text-2xl text-[#fff]" data-story-text></blockquote>
                    <figcaption class="mt-3 text-sm text-[#f2a54a]" data-story-author></figcaption>
                </figure>
            </div>

            <div class="flex items-center gap-3">
                <span class="text-xs text-[#fff]/70" data-story-left></span>
                <form method="POST" class="ms-auto" data-story-delete hidden onsubmit="return confirm('{{ __('Delete this story?') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="story-btn" aria-label="{{ __('Delete story') }}"><x-icon name="trash" /></button>
                </form>
                @auth
                    <button type="button" class="story-like" data-story-like aria-pressed="false" aria-label="{{ __('Like') }}">
                        <x-icon name="heart" /> <span data-story-likes>0</span>
                    </button>
                @else
                    <a href="{{ route('login') }}" class="story-like" aria-label="{{ __('Log in to like') }}"><x-icon name="heart" /> <span data-story-likes>0</span></a>
                @endauth
            </div>
        </div>
    </div>

    @auth
        {{-- new story --}}
        <div id="story-popup" popover class="modal">
            <form method="POST" action="{{ route('stories.store') }}" class="space-y-4 p-6" data-story-form>
                @csrf
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-white">{{ __('New story') }}</h2>
                    <button type="button" popovertarget="story-popup" popovertargetaction="hide" class="action" aria-label="{{ __('Close') }}"><x-icon name="x" /></button>
                </div>

                <div class="story-preview" data-story-preview><p class="quote-text text-xl text-[#fff] qs-align-center" dir="auto" data-story-preview-text>{{ __('Your story will look like this') }}</p></div>

                <div>
                    <label for="story-content" class="label">{{ __('Quote') }}</label>
                    <textarea id="story-content" name="content" rows="3" required minlength="3" maxlength="155" dir="auto" data-counter class="input resize-none" placeholder="{{ __('Write your quote…') }}"></textarea>
                    <p class="hint flex justify-between"><span>{{ __('3–155 characters.') }}</span><span data-counter-output></span></p>
                </div>
                <div>
                    <label for="story-author" class="label">{{ __('Said by') }} <span class="font-normal text-tuwaiq-lilac">({{ __('optional') }})</span></label>
                    <input id="story-author" name="author" maxlength="80" dir="auto" class="input" placeholder="{{ __('e.g. Ibn Khaldun') }}">
                </div>

                <fieldset>
                    <legend class="label">{{ __('How long should it stay?') }}</legend>
                    <div class="flex gap-2">
                        @foreach (\App\Models\Story::LIFETIMES as $hours)
                            <label class="style-chip"><input type="radio" name="hours" value="{{ $hours }}" class="sr-only" @checked($hours === 24)> {{ trans_choice(':count hour|:count hours', $hours, ['count' => $hours]) }}</label>
                        @endforeach
                    </div>
                    <p class="hint">{{ __('After that it disappears by itself. Everyone can see it until then.') }}</p>
                </fieldset>

                <div>
                    <p class="label">{{ __('Font') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (\App\Support\QuoteStyle::FONTS as $key => $label)
                            <label class="style-chip qs-font-{{ $key }}"><input type="radio" name="style[font]" value="{{ $key }}" class="sr-only" @checked($key === 'almarai')> {{ $label }}</label>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="btn-primary w-full">{{ __('Share story') }}</button>
            </form>
        </div>
    @endauth
@endif
