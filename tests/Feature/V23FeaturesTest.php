<?php

use App\Models\Category;
use App\Models\Quote;
use App\Models\User;
use App\Services\RecommendationService;
use App\Services\SmartSearch;
use App\Services\WeeklySummary;
use Illuminate\Support\Facades\DB;

// ---------- scheduled quotes ----------

test('a quote can be scheduled for later', function () {
    $user = User::factory()->create();
    $when = now()->addDay()->setTime(21, 0);

    $this->actingAs($user)
        ->post(route('quotes.store'), ['content' => 'Patience is also a kind of work.', 'publish_at' => $when->format('Y-m-d\TH:i')])
        ->assertRedirect(route('users.show', [$user, 'tab' => 'scheduled']));

    $quote = Quote::withoutGlobalScope('published')->firstWhere('content', 'Patience is also a kind of work.');
    expect($quote->isScheduled())->toBeTrue()
        ->and($quote->published_at->format('Y-m-d H:i'))->toBe($when->format('Y-m-d H:i'));
});

test('a scheduled quote stays hidden from everyone else until its time', function () {
    $owner = User::factory()->create();
    $quote = Quote::factory()->for($owner)->create(['content' => 'Wait for the right moment', 'published_at' => now()->addHours(3)]);

    $this->get('/')->assertDontSee('Wait for the right moment');
    $this->get(route('search', ['q' => 'moment']))->assertDontSee('Wait for the right moment');
    $this->get(route('quotes.show', $quote))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('quotes.show', $quote))->assertNotFound();

    $this->travel(4)->hours();
    auth()->logout();

    $this->get('/')->assertSee('Wait for the right moment');
    $this->get(route('quotes.show', $quote))->assertOk();
});

test('the owner sees scheduled quotes in their own tab and can publish now', function () {
    $owner = User::factory()->create();
    $quote = Quote::factory()->for($owner)->create(['content' => 'Coming tomorrow night', 'published_at' => now()->addDay()]);

    $this->actingAs($owner)->get(route('users.show', [$owner, 'tab' => 'scheduled']))
        ->assertOk()
        ->assertSee('Coming tomorrow night')
        ->assertSee('Only you can see it until then.');

    $this->actingAs($owner)->get(route('quotes.show', $quote))->assertOk();

    $this->actingAs($owner)->post(route('quotes.publish-now', $quote))->assertRedirect();
    expect($quote->fresh()->isScheduled())->toBeFalse();
    $this->get('/')->assertSee('Coming tomorrow night');
});

test('others cannot see the scheduled tab or publish someone else\'s quote', function () {
    $owner = User::factory()->create();
    $quote = Quote::factory()->for($owner)->create(['content' => 'Private draft line', 'published_at' => now()->addDay()]);
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('users.show', [$owner, 'tab' => 'scheduled']))->assertDontSee('Private draft line');
    $this->actingAs($other)->post(route('quotes.publish-now', $quote))->assertNotFound();
    expect($quote->fresh()->isScheduled())->toBeTrue();
});

test('scheduling needs a time in the future and within a year', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('quotes.store'), ['content' => 'Too late now', 'publish_at' => now()->subHour()->format('Y-m-d\TH:i')])
        ->assertSessionHasErrors('publish_at');
    $this->actingAs($user)->post(route('quotes.store'), ['content' => 'Too far away', 'publish_at' => now()->addYears(2)->format('Y-m-d\TH:i')])
        ->assertSessionHasErrors('publish_at');
    expect(Quote::withoutGlobalScope('published')->count())->toBe(0);
});

// ---------- sorting ----------

function sortFixtures(): array
{
    $old = Quote::factory()->create(['content' => 'Old quote here', 'published_at' => now()->subDays(10)]);
    $liked = Quote::factory()->create(['content' => 'Liked quote here', 'published_at' => now()->subDays(5)]);
    $shared = Quote::factory()->create(['content' => 'Shared quote here', 'published_at' => now()->subDays(3)]);
    $talked = Quote::factory()->create(['content' => 'Talked quote here', 'published_at' => now()->subDay()]);

    User::factory()->count(3)->create()->each(fn ($u) => $u->likedQuotes()->attach($liked));
    foreach (['x', 'copy', 'whatsapp', 'image'] as $channel) {
        $shared->shares()->create(['channel' => $channel]);
    }
    $commenter = User::factory()->create();
    foreach (range(1, 3) as $i) {
        $talked->comments()->forceCreate(['user_id' => $commenter->id, 'content' => 'Nice one '.$i]);
    }

    return compact('old', 'liked', 'shared', 'talked');
}

test('the feed can be sorted in every direction', function () {
    $q = sortFixtures();

    $first = fn (string $sort) => Quote::query()->sortBy($sort)->first()->id;

    expect($first('latest'))->toBe($q['talked']->id)
        ->and($first('oldest'))->toBe($q['old']->id)
        ->and($first('popular'))->toBe($q['liked']->id)
        ->and($first('shared'))->toBe($q['shared']->id)
        ->and($first('commented'))->toBe($q['talked']->id)
        ->and($first('interactive'))->toBe($q['talked']->id); // 3 comments x2 beats 4 shares and 3 likes

    $this->get('/?sort=oldest')->assertOk()->assertSeeInOrder(['Old quote here', 'Liked quote here']);
    $this->get('/?sort=shared')->assertOk()->assertSeeInOrder(['Shared quote here', 'Old quote here']);
    $this->get('/?sort=nonsense')->assertOk()->assertSeeInOrder(['Talked quote here', 'Old quote here']);
});

test('search results can be sorted too', function () {
    sortFixtures();

    $this->get(route('search', ['q' => 'quote', 'sort' => 'oldest']))->assertOk()->assertSeeInOrder(['Old quote here', 'Talked quote here']);
    $this->get(route('search', ['q' => 'quote', 'sort' => 'popular']))->assertOk()->assertSeeInOrder(['Liked quote here', 'Old quote here']);
    $this->get(route('search', ['q' => 'quote', 'sort' => 'oops']))->assertSessionHasErrors('sort');
});

// ---------- share tracking ----------

test('shares are counted per channel', function () {
    $quote = Quote::factory()->create();

    $this->post(route('quotes.shared', $quote), ['channel' => 'whatsapp'])->assertNoContent();
    $this->actingAs($user = User::factory()->create())->post(route('quotes.shared', $quote), ['channel' => 'image'])->assertNoContent();

    expect($quote->shares()->count())->toBe(2)
        ->and($quote->shares()->where('user_id', $user->id)->value('channel'))->toBe('image');

    $this->post(route('quotes.shared', $quote), ['channel' => 'pigeon'])->assertSessionHasErrors('channel');
});

test('a scheduled quote cannot be shared yet', function () {
    $quote = Quote::factory()->create(['published_at' => now()->addDay()]);

    $this->post(route('quotes.shared', $quote), ['channel' => 'copy'])->assertNotFound();
});

// ---------- report in both languages ----------

test('the pdf report can be opened in arabic or english', function () {
    Quote::factory()->count(2)->create();

    $this->get(route('insights.report', ['lang' => 'ar']))->assertOk()->assertSee('dir="rtl"', false)->assertSee('English version');
    $this->get(route('insights.report', ['lang' => 'en']))->assertOk()->assertSee('dir="ltr"', false)->assertSee('النسخة العربية');
    $this->get(route('insights'))->assertSee('العربية')->assertSee('English');
});

// ---------- more recommendation reasons ----------

test('recommendations explain similar taste and trending quotes', function () {
    $me = User::factory()->create();
    $twin = User::factory()->create();
    $both = Quote::factory()->create();
    $twinsPick = Quote::factory()->create();

    $me->likedQuotes()->attach($both);
    $twin->likedQuotes()->attach([$both->id, $twinsPick->id]);

    $rows = app(RecommendationService::class)->recommend($me);
    $row = $rows->firstWhere('quote.id', $twinsPick->id);

    expect($row['reasons'])->toContain('Recommended because people with a taste like yours liked it');

    $hot = Quote::factory()->create();
    User::factory()->count(3)->create()->each(fn ($u) => $u->likedQuotes()->attach($hot));
    $stranger = User::factory()->create();

    $hotRow = app(RecommendationService::class)->recommend($stranger, 20)->firstWhere('quote.id', $hot->id);
    expect($hotRow['reason'])->toBe('Trending this week: liked 3 times in 7 days');
});

// ---------- smart search with different wording ----------

test('smart search understands dialect, typos, word endings and english', function (string $query, string $topic) {
    expect(app(SmartSearch::class)->understand($query)['topics'])->toContain($topic);
})->with([
    'saudi dialect' => ['ابي شي يرفع معنوياتي وانا طفشان', 'laziness'],
    'feeling tired' => ['تعبان من الدوام', 'tired'],
    'with a suffix' => ['اختباراتي بكره وانا متوتره', 'anxiety'],
    'with al and prefixes' => ['وبالنجاح', 'success'],
    'small typo' => ['الصداقة الحقيقيه', 'friendship'],
    'typo in english' => ['motivaton please', 'motivation'],
    'english sentence' => ['I feel lonely tonight', 'loneliness'],
    'english ending' => ['dreaming big', 'dreams'],
    'feeling lost' => ['حاسه اني ضايعه', 'lost'],
    'job interview' => ['عندي مقابله وظيفيه بكره', 'work'],
    'want to laugh' => ['ابي اضحك', 'humor'],
    'need to calm down' => ['احتاج شي يهديني', 'calm'],
]);

test('an exact word is not mistaken for a similar one', function () {
    $search = app(SmartSearch::class);

    expect($search->understand('اختبار')['topics'])->toBe(['study'])
        ->and($search->understand('محبطة')['topics'])->toBe(['sadness']);
});

test('search falls back to the words of the quote when no topic matches', function () {
    Quote::factory()->create(['content' => 'The sea teaches patience to the shore']);

    $this->get(route('search', ['q' => 'shore']))->assertOk()->assertSee('The sea teaches patience to the shore');
});

// ---------- final touches ----------

test('a scheduled quote has no likes, saves or comments until it is published', function () {
    $owner = User::factory()->create();
    $quote = Quote::factory()->for($owner)->create(['content' => 'Not out yet', 'published_at' => now()->addDay()]);

    $this->actingAs($owner)->get(route('quotes.show', $quote))
        ->assertOk()
        ->assertDontSee(route('quotes.like', $quote))
        ->assertDontSee(route('quotes.bookmark', $quote))
        ->assertDontSee(route('comments.store', $quote));

    $this->actingAs($owner)->post(route('quotes.like', $quote))->assertForbidden();
    $this->actingAs($owner)->post(route('quotes.bookmark', $quote))->assertForbidden();
    $this->actingAs($owner)->post(route('comments.store', $quote), ['content' => 'early'])->assertForbidden();

    $this->travel(2)->days();
    $this->actingAs($owner)->get(route('quotes.show', $quote))->assertSee(route('quotes.like', $quote));
});

test('extra recommendation reasons use the short wording', function () {
    $me = User::factory()->create();
    $twin = User::factory()->create();
    $love = Category::create(['name' => 'Love', 'slug' => 'love']);
    $both = Quote::factory()->create(['category_id' => $love->id]);
    $pick = Quote::factory()->create(['category_id' => $love->id]);
    $me->likedQuotes()->attach($both);
    $twin->likedQuotes()->attach([$both->id, $pick->id]);

    $row = app(RecommendationService::class)->recommend($me)->firstWhere('quote.id', $pick->id);

    expect(count($row['reasons']))->toBeGreaterThan(1);
    foreach ($row['extra'] as $extra) {
        expect($extra)->not->toStartWith('Recommended because');
    }
});

test('the owner sees a summary of their week on their profile', function () {
    $me = User::factory()->create();
    $love = Category::create(['name' => 'Love', 'slug' => 'love']);
    $star = Quote::factory()->for($me)->create(['content' => 'My busiest quote', 'category_id' => $love->id]);
    Quote::factory()->for($me)->create(['category_id' => $love->id]);

    User::factory()->count(3)->create()->each(fn ($u) => $u->likedQuotes()->attach($star));
    $star->shares()->create(['channel' => 'whatsapp']);

    $old = User::factory()->create();
    $old->likedQuotes()->attach($star);
    DB::table('likes')->where('user_id', $old->id)->update(['created_at' => now()->subDays(10)]);

    $summary = app(WeeklySummary::class)->for($me);
    expect($summary['likes'])->toBe(3)
        ->and($summary['likes_before'])->toBe(1)
        ->and($summary['shares'])->toBe(1)
        ->and($summary['new_quotes'])->toBe(2)
        ->and($summary['top_quote']->id)->toBe($star->id)
        ->and($summary['top_topic'])->toBe('Love');

    $this->actingAs($me)->get(route('users.show', $me))
        ->assertSee('Your week')
        ->assertSee('2 more than the week before')
        ->assertSee('My busiest quote');

    $this->actingAs(User::factory()->create())->get(route('users.show', $me))->assertDontSee('Your week');
});

test('every page shows the copyright line', function () {
    $this->get('/')->assertSee('Wajd Alluhaibi. All rights reserved.');
    $this->get(route('login'))->assertSee('Wajd Alluhaibi');
});

test('the reading bar offers every speed and saving a speed from it is kept on the account', function () {
    $user = User::factory()->create();

    $page = $this->actingAs($user)->get('/')->assertOk()->assertSee('id="speech-bar"', false);
    foreach (['0.75', '1', '1.25', '1.5', '2'] as $rate) {
        $page->assertSee('data-rate="'.$rate.'"', false);
    }

    $this->actingAs($user)->putJson(route('accessibility.update'), ['speech_rate' => 1.5])->assertOk();
    $this->actingAs($user)->get('/')->assertSee('data-speech-rate="1.5"', false);
});

test('the site has one dark look and no light mode switch', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')
        ->assertDontSee('data-theme-toggle', false)
        ->assertDontSee('images/logo-light.png', false)
        ->assertSee('images/logo.png', false);

    $this->actingAs($user)->putJson(route('accessibility.update'), ['theme' => 'light'])->assertOk();
    expect($user->fresh()->preferences)->not->toHaveKey('theme');
});

test('the top bar has a link back to the home page', function () {
    $this->get(route('search'))->assertSee('href="'.route('home').'"', false)->assertSee('Home');
});

// ---------- v2.4: submission rules and safety ----------

test('a quote can have at most 155 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('quotes.store'), ['content' => str_repeat('a', 156)])->assertSessionHasErrors('content');
    $this->actingAs($user)->post(route('quotes.store'), ['content' => str_repeat('a', 155)])->assertSessionDoesntHaveErrors();
    $this->actingAs($user)->get('/')->assertSee('maxlength="155"', false);
});

test('login stops after 5 wrong passwords in a minute', function () {
    $user = User::factory()->create(['email' => 'sara@example.com']);

    foreach (range(1, 5) as $try) {
        $this->post('/login', ['email' => 'sara@example.com', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
    }

    $this->post('/login', ['email' => 'sara@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith('Too many tries.');
    $this->assertGuest();
});

test('the insights report is built on a layout like every other page', function () {
    $this->get(route('insights.report'))->assertOk()->assertSee('class="sheet"', false)->assertSee('Download PDF');
    expect(file_get_contents(resource_path('views/insights/report.blade.php')))->toContain("@extends('Layout.print')");
});
