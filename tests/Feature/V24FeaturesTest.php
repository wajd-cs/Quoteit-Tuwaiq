<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Quote;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\Http;

// ---------- messages ----------

test('two people can chat and nobody else can read it', function () {
    $sara = User::factory()->create(['name' => 'Sara']);
    $noura = User::factory()->create(['name' => 'Noura']);
    $stranger = User::factory()->create();

    $this->actingAs($sara)->post(route('messages.start', $noura))->assertRedirect();
    $conversation = Conversation::first();

    $this->actingAs($sara)->postJson(route('messages.store', $conversation), ['body' => 'Hello Noura'])
        ->assertCreated()->assertJsonPath('message.mine', true);

    $this->actingAs($noura)->get(route('messages.index'))->assertSee('Hello Noura')->assertSee('Sara');
    $this->actingAs($noura)->get(route('messages.show', $conversation))->assertOk()->assertSee('Hello Noura');
    expect(Message::first()->read_at)->not->toBeNull();

    $this->actingAs($stranger)->get(route('messages.show', $conversation))->assertForbidden();
    $this->actingAs($stranger)->postJson(route('messages.store', $conversation), ['body' => 'hi'])->assertForbidden();
    $this->actingAs($stranger)->getJson(route('messages.poll', $conversation))->assertForbidden();
});

test('the same two people always share one conversation', function () {
    [$a, $b] = User::factory()->count(2)->create();

    $this->actingAs($a)->post(route('messages.start', $b));
    $this->actingAs($b)->post(route('messages.start', $a));

    expect(Conversation::count())->toBe(1);
});

test('new messages arrive without reloading and the unread number updates', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = Conversation::between($a, $b);

    $this->actingAs($a)->postJson(route('messages.store', $conversation), ['body' => 'First']);
    $this->actingAs($b)->getJson(route('live'))->assertJson(['messages' => 1]);

    $this->actingAs($b)->getJson(route('messages.poll', [$conversation, 'after' => 0]))
        ->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'First');
    $this->actingAs($b)->getJson(route('live'))->assertJson(['messages' => 0]);
});

test('messages cannot be empty or too long, and there is no sending limit', function () {
    [$a, $b] = User::factory()->count(2)->create();
    $conversation = Conversation::between($a, $b);

    $this->actingAs($a)->postJson(route('messages.store', $conversation), ['body' => ''])->assertUnprocessable();
    $this->actingAs($a)->postJson(route('messages.store', $conversation), ['body' => str_repeat('a', 1001)])->assertUnprocessable();

    foreach (range(1, 25) as $i) {
        $this->actingAs($a)->postJson(route('messages.store', $conversation), ['body' => "m{$i}"])->assertCreated();
    }
    expect(Message::count())->toBe(25);
});

// ---------- notifications ----------

test('the bell shows quotes from people I follow only when I turn it on', function () {
    $me = User::factory()->create();
    $friend = User::factory()->create(['name' => 'Faisal']);
    $me->following()->attach($friend);
    Quote::factory()->for($friend)->create(['content' => 'Fresh words from Faisal']);

    $this->actingAs($me)->get('/')->assertDontSee('posted a new quote');
    $this->actingAs($me)->getJson(route('live'))->assertJson(['notifications' => 0]);

    $this->actingAs($me)->put(route('profile.update'), ['name' => 'Wajd', 'notify_new_quotes' => '1'])->assertSessionHasNoErrors();
    expect($me->fresh()->notify_new_quotes)->toBeTrue();

    $this->actingAs($me)->get('/')->assertSee('Fresh words from Faisal')->assertSee('posted a new quote');
    $this->actingAs($me)->getJson(route('live'))->assertJson(['notifications' => 1]);

    $this->actingAs($me)->post(route('notifications.seen'))->assertNoContent();
    $this->actingAs($me)->getJson(route('live'))->assertJson(['notifications' => 0]);
});

test('a scheduled quote reaches the bell only once it is published', function () {
    $me = User::factory()->create(['notify_new_quotes' => true]);
    $friend = User::factory()->create();
    $me->following()->attach($friend);
    Quote::factory()->for($friend)->create(['content' => 'Coming later', 'published_at' => now()->addHour()]);

    $this->actingAs($me)->getJson(route('live'))->assertJson(['notifications' => 0]);
    $this->travel(2)->hours();
    $this->actingAs($me)->getJson(route('live'))->assertJson(['notifications' => 1]);
    $this->actingAs($me)->get(route('messages.index'))->assertSee('Coming later');
});

// ---------- drafts ----------

test('a quote can be saved as a draft, edited and published later', function () {
    $me = User::factory()->create();

    $this->actingAs($me)->post(route('quotes.store'), ['content' => 'Half finished thought', 'draft' => '1'])
        ->assertRedirect(route('users.show', [$me, 'tab' => 'drafts']));

    $draft = Quote::withoutGlobalScope('published')->first();
    expect($draft->is_draft)->toBeTrue()->and($draft->published_at)->toBeNull();

    auth()->logout();
    $this->get('/')->assertDontSee('Half finished thought');
    $this->get(route('quotes.show', $draft))->assertNotFound();
    $this->actingAs($me)->get(route('users.show', [$me, 'tab' => 'drafts']))->assertSee('Half finished thought');

    $this->actingAs($me)->put(route('edit_quote', $draft), ['content' => 'A finished thought', 'draft' => '1']);
    expect($draft->fresh()->is_draft)->toBeTrue();

    $this->actingAs($me)->put(route('edit_quote', $draft), ['content' => 'A finished thought'])->assertRedirect(route('quotes.show', $draft));
    expect($draft->fresh()->is_draft)->toBeFalse();
    $this->get('/')->assertSee('A finished thought');
});

test('drafts cannot be liked, saved or seen by others', function () {
    $draft = Quote::factory()->create(['is_draft' => true, 'published_at' => null]);
    $other = User::factory()->create();

    $this->actingAs($other)->post(route('quotes.like', $draft))->assertNotFound();
    $this->actingAs($other)->get(route('users.show', [$draft->user, 'tab' => 'drafts']))->assertDontSee($draft->content);
});

// ---------- translation ----------

test('a quote is translated inside the site and the translation is saved', function () {
    Http::fake([
        'api.mymemory.translated.net/*' => Http::response([
            'responseStatus' => 200,
            'responseData' => ['translatedText' => 'Patience is the key to relief'],
        ]),
    ]);
    $quote = Quote::factory()->create(['content' => 'الصبر مفتاح الفرج']);

    $this->getJson(route('quotes.translate', $quote))->assertOk()
        ->assertJson(['text' => 'Patience is the key to relief', 'lang' => 'en']);
    $this->getJson(route('quotes.translate', $quote))->assertOk();

    Http::assertSentCount(1);
    expect($quote->translations()->value('text'))->toBe('Patience is the key to relief');
});

test('translation explains itself when the service is down', function () {
    Http::fake(['*' => Http::response('', 500)]);
    $quote = Quote::factory()->create(['content' => 'Stay hungry, stay foolish']);

    $this->getJson(route('quotes.translate', $quote))->assertStatus(503)->assertJsonStructure(['message']);
});

// ---------- stories ----------

test('a story stays up for the hours its owner picked and everyone can see it', function () {
    $me = User::factory()->create(['name' => 'Wajd']);

    $this->actingAs($me)->post(route('stories.store'), ['content' => 'Small steps every day', 'hours' => 12])->assertRedirect(route('home'));
    $story = Story::first();
    expect((int) round(now()->diffInHours($story->expires_at)))->toBe(12);

    auth()->logout();
    $this->get('/')->assertSee('Small steps every day')->assertSee('data-story-open', false);

    $this->travel(13)->hours();
    $this->get('/')->assertDontSee('Small steps every day');
});

test('a story only lasts 12 or 24 hours and must be 3 to 155 characters', function () {
    $me = User::factory()->create();

    $this->actingAs($me)->post(route('stories.store'), ['content' => 'Hello there', 'hours' => 48])->assertSessionHasErrors('hours');
    $this->actingAs($me)->post(route('stories.store'), ['content' => str_repeat('a', 156), 'hours' => 24])->assertSessionHasErrors('content');
    expect(Story::count())->toBe(0);
});

test('people can like a story and only the owner can delete it', function () {
    $story = Story::create(['user_id' => User::factory()->create()->id, 'content' => 'Be kind', 'expires_at' => now()->addDay()]);
    $fan = User::factory()->create();

    $this->actingAs($fan)->postJson(route('stories.like', $story))->assertJson(['active' => true, 'count' => 1]);
    $this->actingAs($fan)->postJson(route('stories.like', $story))->assertJson(['active' => false, 'count' => 0]);

    $this->actingAs($fan)->delete(route('stories.destroy', $story))->assertForbidden();
    $this->actingAs($story->user)->delete(route('stories.destroy', $story))->assertRedirect();
    expect(Story::count())->toBe(0);
});

// ---------- quote look ----------

test('the chosen font, size and colour are saved and shown on the card', function () {
    $me = User::factory()->create();

    $this->actingAs($me)->post(route('quotes.store'), [
        'content' => 'Styled words',
        'style' => ['font' => 'cairo', 'size' => 'l', 'color' => 'teal', 'align' => 'center'],
    ]);
    $quote = Quote::first();
    expect($quote->style)->toBe(['font' => 'cairo', 'size' => 'l', 'color' => 'teal', 'align' => 'center']);
    $this->get(route('quotes.show', $quote))->assertSee('qs-font-cairo qs-size-l qs-color-teal qs-align-center', false);

    $this->actingAs($me)->post(route('quotes.store'), ['content' => 'Odd style', 'style' => ['font' => 'comic', 'color' => '#ff0000']]);
    expect(Quote::latest('id')->first()->style)->toBeNull();
});

// ---------- demo data on the live site ----------

test('refreshing the demo data swaps old demo accounts and never touches real ones', function () {
    $real = User::factory()->create(['email' => 'wajd@example.com']);
    $realQuote = Quote::factory()->for($real)->create();
    $oldDemo = User::factory()->create(['email' => 'old.demo@quoteit.test']);
    Quote::factory()->for($oldDemo)->create();

    $this->artisan('quoteit:demo-data')->assertSuccessful();

    expect(User::where('email', 'old.demo@quoteit.test')->exists())->toBeFalse()
        ->and($real->fresh())->not->toBeNull()
        ->and($realQuote->fresh())->not->toBeNull()
        ->and(User::where('email', 'demo@quoteit.test')->exists())->toBeTrue()
        ->and(Quote::count())->toBeGreaterThan(200)
        ->and(Story::active()->exists())->toBeTrue();

    // running it again changes nothing
    $users = User::count();
    $this->artisan('quoteit:demo-data')->expectsOutput('Demo data is up to date.');
    expect(User::count())->toBe($users);
});

test('the open chat shows the right person even with several conversations', function () {
    $me = User::factory()->create();
    $sara = User::factory()->create(['name' => 'Sara']);
    $omar = User::factory()->create(['name' => 'Omar']);
    $withSara = Conversation::between($me, $sara);
    $withOmar = Conversation::between($me, $omar);
    $withSara->update(['last_message_at' => now()->subDay()]);
    $withOmar->update(['last_message_at' => now()]);

    $this->actingAs($me)->get(route('messages.show', $withSara))
        ->assertOk()
        ->assertSee('aria-label="'.__('Chat with :name', ['name' => 'Sara']).'"', false);
});

test('guests get the tour too and can replay it from the footer', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Welcome to QuoteIt')
        ->assertSee('data-tour-start', false)
        ->assertSee('data-tour="signup"', false);
});

test('a new message shows the people you follow, or anyone found by name', function () {
    $me = User::factory()->create();
    $friend = User::factory()->create(['name' => 'Sara']);
    User::factory()->create(['name' => 'Omar']);

    $this->actingAs($me)->get(route('messages.people'))
        ->assertOk()
        ->assertSee(__('No friends to message yet. Follow people, or search for a name above.'));

    $me->following()->attach($friend);
    $this->actingAs($me)->get(route('messages.index'))->assertSee('id="new-chat"', false)->assertSee('Sara');
    $this->actingAs($me)->get(route('messages.people'))->assertSee('Sara')->assertDontSee('Omar');
    $this->actingAs($me)->get(route('messages.people', ['q' => 'oma']))->assertSee('Omar')->assertSee(route('messages.start', User::firstWhere('name', 'Omar')), false);
    $this->actingAs($me)->get(route('messages.people', ['q' => 'nobody']))->assertSee(__('No one found with this name.'));
    auth()->logout();
    $this->get(route('messages.people'))->assertRedirect(route('login'));
});
