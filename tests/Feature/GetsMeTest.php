<?php

use App\Models\Category;
use App\Models\Goal;
use App\Models\Mood;
use App\Models\Quote;
use App\Models\QuotePick;
use App\Models\User;

// moods and goals are inserted by their migration, so they exist in every test

test('guests are sent to log in', function () {
    $this->get(route('gets-me'))->assertRedirect(route('login'));
    $this->get(route('gets-me.suggest', ['mood' => 'sad', 'goal' => 'hope']))->assertRedirect(route('login'));
    $this->get(route('gets-me.history'))->assertRedirect(route('login'));
});

test('the page lists all six feelings and five goals', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('gets-me'))
        ->assertOk()
        ->assertSee(['سعيد', 'حزين', 'متوتر', 'فاقد للشغف', 'متحمس', 'هادئ'])
        ->assertSee(['التحفيز', 'المواساة', 'الأمل', 'الهدوء', 'الثقة بالنفس']);
});

test('mood and goal are required and must exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('gets-me.suggest'))
        ->assertStatus(422)->assertJsonValidationErrors(['mood', 'goal']);

    $this->actingAs($user)->getJson(route('gets-me.suggest', ['mood' => 'angry', 'goal' => 'hope']))
        ->assertStatus(422)->assertJsonValidationErrors('mood');
});

test('tagged quotes are suggested first and unrelated quotes are not', function () {
    $sad = Mood::firstWhere('slug', 'sad');
    $hope = Goal::firstWhere('slug', 'hope');

    $perfect = Quote::factory()->create(['content' => 'Perfect match quote']);
    $perfect->moods()->attach($sad);
    $perfect->goals()->attach($hope);

    $goalOnly = Quote::factory()->create(['content' => 'Goal only quote']);
    $goalOnly->goals()->attach($hope);

    Quote::factory()->create(['content' => 'Has nothing to do with it']);

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('gets-me.suggest', ['mood' => 'sad', 'goal' => 'hope']))
        ->assertOk()
        ->assertJsonPath('count', 2);

    expect($response->json('ids'))->toBe([$perfect->id, $goalOnly->id])
        ->and($response->json('html'))->toContain('Perfect match quote')->not->toContain('Has nothing to do with it');
});

test('quotes in a category linked to the goal are suggested too', function () {
    $faith = Category::create(['name' => 'Faith', 'slug' => 'faith']);
    Goal::firstWhere('slug', 'comfort')->categories()->attach($faith);
    Quote::factory()->create(['content' => 'Untagged faith quote', 'category_id' => $faith->id]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('gets-me.suggest', ['mood' => 'sad', 'goal' => 'comfort']))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertSee('From a related category');
});

test('no match shows the empty message', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('gets-me.suggest', ['mood' => 'happy', 'goal' => 'confidence']))
        ->assertOk()
        ->assertSee('yet.');
});

test('show me others skips quotes already seen', function () {
    $goal = Goal::firstWhere('slug', 'motivation');
    $quotes = Quote::factory()->count(4)->create();
    $quotes->each(fn ($q) => $q->goals()->attach($goal));
    $seen = $quotes->take(3)->pluck('id')->all();

    $this->actingAs(User::factory()->create())
        ->getJson(route('gets-me.suggest', ['mood' => 'unmotivated', 'goal' => 'motivation', 'except' => $seen]))
        ->assertJsonPath('ids', [$quotes[3]->id]);
});

test('picking a quote saves it to history once per day', function () {
    $user = User::factory()->create();
    $quote = Quote::factory()->create();
    $payload = ['quote_id' => $quote->id, 'mood' => 'anxious', 'goal' => 'peace'];

    $this->actingAs($user)->postJson(route('gets-me.pick'), $payload)->assertCreated();
    $this->actingAs($user)->postJson(route('gets-me.pick'), $payload)->assertOk();

    expect($user->quotePicks()->count())->toBe(1);
    $pick = $user->quotePicks()->with(['mood', 'goal'])->first();
    expect($pick->mood->slug)->toBe('anxious')->and($pick->goal->slug)->toBe('peace');

    $this->actingAs($user)->get(route('gets-me.history'))
        ->assertOk()
        ->assertSee($quote->content)
        ->assertSee('Anxious')
        ->assertSee($pick->created_at->translatedFormat('j M Y'));
});

test('history is private to each user', function () {
    $quote = Quote::factory()->create(['content' => 'My private pick']);
    $owner = User::factory()->create();
    $pick = $owner->quotePicks()->create([
        'quote_id' => $quote->id,
        'mood_id' => Mood::firstWhere('slug', 'sad')->id,
        'goal_id' => Goal::firstWhere('slug', 'hope')->id,
    ]);
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('gets-me.history'))->assertOk()->assertDontSee('My private pick');
    $this->actingAs($other)->delete(route('gets-me.destroy', $pick))->assertForbidden();
    expect(QuotePick::find($pick->id))->not->toBeNull();

    $this->actingAs($owner)->delete(route('gets-me.destroy', $pick))->assertRedirect();
    expect(QuotePick::find($pick->id))->toBeNull();
});

test('a suggested quote can be saved to favourites with the existing bookmark', function () {
    $user = User::factory()->create();
    $quote = Quote::factory()->create();
    $quote->goals()->attach(Goal::firstWhere('slug', 'hope'));

    $html = $this->actingAs($user)->getJson(route('gets-me.suggest', ['mood' => 'sad', 'goal' => 'hope']))->json('html');
    expect($html)->toContain(route('quotes.bookmark', $quote));

    $this->actingAs($user)->postJson(route('quotes.bookmark', $quote))->assertJson(['active' => true]);
    expect($user->bookmarkedQuotes()->whereKey($quote->id)->exists())->toBeTrue();
});

test('sharing a quote can tag moods and goals, and editing keeps them in sync', function () {
    $user = User::factory()->create();
    $sad = Mood::firstWhere('slug', 'sad');
    $hope = Goal::firstWhere('slug', 'hope');
    $comfort = Goal::firstWhere('slug', 'comfort');

    $this->actingAs($user)->post(route('quotes.store'), [
        'content' => 'Tagged by me',
        'feelings_section' => 1,
        'moods' => [$sad->id],
        'goals' => [$hope->id],
    ])->assertRedirect();

    $quote = $user->quotes()->first();
    expect($quote->moods->pluck('id')->all())->toBe([$sad->id])
        ->and($quote->goals->pluck('id')->all())->toBe([$hope->id]);

    $this->actingAs($user)->put(route('edit_quote', $quote), [
        'content' => 'Tagged by me',
        'feelings_section' => 1,
        'goals' => [$comfort->id],
    ]);

    $quote->refresh();
    expect($quote->moods)->toHaveCount(0)->and($quote->goals->pluck('id')->all())->toBe([$comfort->id]);
});

test('editing without the tags section does not remove tags', function () {
    $quote = Quote::factory()->create();
    $quote->goals()->attach(Goal::firstWhere('slug', 'hope'));

    $this->actingAs($quote->user)->put(route('edit_quote', $quote), ['content' => 'New text here']);

    expect($quote->goals()->count())->toBe(1);
});
