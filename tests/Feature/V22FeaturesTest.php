<?php

use App\Models\Category;
use App\Models\Goal;
use App\Models\Mood;
use App\Models\Quote;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\SmartSearch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// ---------- Arabic / English ----------

test('switching to arabic turns the whole page right-to-left', function () {
    $this->get(route('locale', 'ar'))->assertRedirect();

    $this->get('/')
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('lang="ar"', false)
        ->assertSee('كل الاقتباسات');

    $this->get(route('locale', 'en'));
    $this->get('/')->assertSee('dir="ltr"', false)->assertSee('All quotes');
});

test('a signed-in user keeps their language on their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('locale', 'ar'));

    expect($user->fresh()->locale)->toBe('ar');
});

test('unknown languages are refused', function () {
    $this->get('/locale/fr')->assertNotFound();
});

test('category names are shown in arabic', function () {
    $c = Category::create(['name' => 'Success', 'name_ar' => 'نجاح', 'slug' => 'success']);

    $this->withSession(['locale' => 'ar'])->get('/')->assertSee('نجاح');
});

test('validation messages are in arabic', function () {
    $this->withSession(['locale' => 'ar'])
        ->from('/signup')
        ->post('/signup', ['name' => '', 'email' => 'x', 'password' => '1'])
        ->assertSessionHasErrors('name');

    expect(session('errors')->first('name'))->toContain('مطلوب');
});

// ---------- accessibility center ----------

test('accessibility settings are saved to the account and applied on every page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('accessibility.update'), [
        'contrast' => 'high',
        'colors' => 'deuteranopia',
        'speech_rate' => 1.5,
        'text_scale' => 1.3,
        'reduce_motion' => true,
    ])->assertOk()->assertJson(['stored' => 'account']);

    $prefs = $user->fresh()->preferences;
    expect($prefs['contrast'])->toBe('high')
        ->and($prefs['colors'])->toBe('deuteranopia')
        ->and((float) $prefs['speech_rate'])->toBe(1.5);

    $this->actingAs($user)->get('/')
        ->assertSee('data-contrast="high"', false)
        ->assertSee('data-colors="deuteranopia"', false)
        ->assertSee('data-speech-rate="1.5"', false)
        ->assertSee('data-motion="reduce"', false);
});

test('invalid accessibility values are ignored', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('accessibility.update'), ['contrast' => 'neon', 'speech_rate' => 9])->assertOk();

    expect($user->fresh()->preferences ?? [])->not->toHaveKey('speech_rate', 9);
    $this->actingAs($user)->get('/')->assertSee('data-contrast="normal"', false);
});

test('guests can open the accessibility center and their settings stay in the browser', function () {
    $this->get(route('accessibility'))->assertOk()->assertSee('Accessibility center');
    $this->putJson(route('accessibility.update'), ['contrast' => 'high'])->assertJson(['stored' => 'browser']);
});

// ---------- profile picture ----------

test('a user can upload, see and remove a profile picture', function () {
    Storage::fake('local');
    $user = User::factory()->create(['name' => 'Wajd Test']);

    $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Wajd Test',
        'avatar' => UploadedFile::fake()->image('me.png', 300, 300),
    ])->assertRedirect();

    $user->refresh();
    expect($user->avatar_path)->not->toBeNull();
    Storage::disk('local')->assertExists($user->avatar_path);
    $this->get(route('users.avatar', $user))->assertOk();
    $this->get(route('users.show', $user))->assertSee($user->avatarUrl(), false);

    $this->actingAs($user)->put(route('profile.update'), ['name' => 'Wajd Test', 'remove_avatar' => 1]);
    expect($user->fresh()->avatar_path)->toBeNull();
});

test('only images up to 2 MB are accepted as profile pictures', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Test User',
        'avatar' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('avatar');

    $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Test User',
        'avatar' => UploadedFile::fake()->image('big.jpg')->size(3000),
    ])->assertSessionHasErrors('avatar');
});

// ---------- smart recommendations ----------

test('recommendations learn from liked quotes and explain why', function () {
    $motivation = Category::create(['name' => 'Motivation', 'slug' => 'motivation']);
    $love = Category::create(['name' => 'Love', 'slug' => 'love']);
    $user = User::factory()->create();

    $liked = Quote::factory()->count(2)->create(['category_id' => $motivation->id]);
    $user->likedQuotes()->attach($liked->pluck('id'));
    Quote::factory()->create(['content' => 'Unseen motivation quote', 'category_id' => $motivation->id]);
    Quote::factory()->create(['content' => 'A love quote', 'category_id' => $love->id]);

    $response = $this->actingAs($user)->get(route('recommendations'))->assertOk();
    $items = $response->viewData('items');

    expect($items->first()['quote']->content)->toBe('Unseen motivation quote')
        ->and($items->first()['reason'])->toBe('Recommended because you like Motivation quotes')
        ->and($items->pluck('quote.id'))->not->toContain($liked->first()->id);

    $this->actingAs($user)->withSession(['locale' => 'ar'])->get(route('recommendations'))
        ->assertSee('اقترحناه لك لأنك تحب اقتباسات');
});

test('recommendations need an account', function () {
    $this->get(route('recommendations'))->assertRedirect(route('login'));
});

// ---------- smart search ----------

test('smart search understands a sentence in arabic', function () {
    $success = Category::create(['name' => 'Success', 'slug' => 'success']);
    Quote::factory()->create(['content' => 'Success is not final, failure is not fatal.', 'category_id' => $success->id]);
    Quote::factory()->create(['content' => 'الفشل بداية النجاح']);
    Quote::factory()->create(['content' => 'The sea is blue today']);

    $response = $this->get(route('search', ['q' => 'ابغى اقتباس عن النجاح بعد الفشل']))->assertOk();
    $understood = $response->viewData('understood');
    $found = $response->viewData('results')->getCollection()->pluck('quote.content');

    expect($understood['topics'])->toContain('success', 'failure')
        ->and($understood['keywords'])->not->toContain('ابغى', 'اقتباس', 'عن', 'بعد')
        ->and($found)->toContain('الفشل بداية النجاح', 'Success is not final, failure is not fatal.')
        ->and($found)->not->toContain('The sea is blue today');
});

test('smart search finds quotes by author and topic filters', function () {
    $wisdom = Category::create(['name' => 'Wisdom', 'slug' => 'wisdom']);
    Quote::factory()->create(['content' => 'Know thyself', 'author' => 'Socrates', 'category_id' => $wisdom->id]);
    Quote::factory()->create(['content' => 'Another one', 'author' => 'Someone']);

    $this->get(route('search', ['author' => 'Socrates']))->assertSee('Know thyself')->assertDontSee('Another one');
    $this->get(route('search', ['category' => 'wisdom']))->assertSee('Know thyself')->assertDontSee('Another one');
});

test('arabic text is normalised for searching', function () {
    expect(SmartSearch::normalize('النَّجَاحُ'))->toBe('النجاح')
        ->and(SmartSearch::normalize('أمل'))->toBe('امل')
        ->and(SmartSearch::stem('والنجاح'))->toBe('نجاح');
});

// ---------- achievements ----------

test('sharing a first quote earns a badge and points', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('quotes.store'), ['content' => 'My very first quote'])
        ->assertSessionHas('badges');

    expect(DB::table('user_achievements')->where('user_id', $user->id)->pluck('badge'))->toContain('first_quote');
    expect(app(AchievementService::class)->points($user))->toBeGreaterThanOrEqual(10);

    $this->actingAs($user)->get(route('achievements'))->assertOk()->assertSee('First words');
});

test('ten quotes earn the quote writer badge', function () {
    $user = User::factory()->create();
    Quote::factory()->count(10)->for($user)->create();

    app(AchievementService::class)->sync($user);

    expect(DB::table('user_achievements')->where('user_id', $user->id)->pluck('badge'))->toContain('ten_quotes');
});

test('the daily challenge is completed once per day', function () {
    $user = User::factory()->create();
    $key = AchievementService::todaysChallenge();
    [$stat, $target] = AchievementService::CHALLENGES[$key];

    // do exactly what today's challenge asks
    $quotes = Quote::factory()->count(3)->create();
    match ($stat) {
        'quotes' => Quote::factory()->count($target)->for($user)->create(),
        'likes_given' => $user->likedQuotes()->attach($quotes->take($target)->pluck('id')),
        'saves' => $user->bookmarkedQuotes()->attach($quotes->take($target)->pluck('id')),
        'comments' => $user->comments()->create(['quote_id' => $quotes[0]->id, 'content' => 'Nice one']),
        'following' => $user->following()->attach(User::factory()->create()),
        'picks' => $user->quotePicks()->create(['quote_id' => $quotes[0]->id, 'mood_id' => Mood::first()->id, 'goal_id' => Goal::first()->id]),
        'designs' => DB::table('quote_designs')->insert(['user_id' => $user->id, 'quote_id' => $quotes[0]->id, 'created_at' => now(), 'updated_at' => now()]),
    };

    $service = app(AchievementService::class);
    $service->sync($user);
    $service->sync($user);

    expect(DB::table('challenge_completions')->where('user_id', $user->id)->count())->toBe(1);
});

// ---------- design studio ----------

test('the design studio opens for any quote and counts finished designs', function () {
    $quote = Quote::factory()->create(['content' => 'Design me']);
    $user = User::factory()->create();

    $this->get(route('studio', $quote))->assertOk()->assertSee('Design me')->assertSee('data-studio', false);

    $this->postJson(route('studio.designed', $quote))->assertUnauthorized();
    $this->actingAs($user)->postJson(route('studio.designed', $quote), ['template' => 'sunset'])->assertOk()->assertJson(['saved' => true]);

    expect(DB::table('quote_designs')->where('user_id', $user->id)->count())->toBe(1);
});

// ---------- dashboard filters, explanations and PDF report ----------

test('dashboard filters by date range and topic', function () {
    $love = Category::create(['name' => 'Love', 'slug' => 'love']);
    $old = Quote::factory()->create(['content' => 'Old quote']);
    $old->forceFill(['created_at' => now()->subDays(40), 'published_at' => now()->subDays(40)])->save();
    Quote::factory()->create(['content' => 'New love quote', 'category_id' => $love->id]);
    Quote::factory()->create(['content' => 'New other quote']);

    expect($this->get(route('insights', ['range' => '7']))->viewData('kpis')['quotes'])->toBe(2);
    expect($this->get(route('insights', ['range' => '7', 'category' => 'love']))->viewData('kpis')['quotes'])->toBe(1);
    expect($this->get(route('insights', ['range' => 'custom', 'from' => now()->subDays(50)->toDateString(), 'to' => now()->subDays(30)->toDateString()]))->viewData('kpis')['quotes'])->toBe(1);
    expect($this->get(route('insights'))->viewData('kpis')['quotes'])->toBe(3);
});

test('dashboard explains the engagement rate in words, without a formula', function () {
    $quotes = Quote::factory()->count(4)->create();
    User::factory()->create()->likedQuotes()->attach($quotes->first());

    $this->get(route('insights'))
        ->assertSee('How is the engagement rate calculated?')
        ->assertSee('A quote counts as “liked” if it got at least one like, no matter how many.', false)
        ->assertDontSee('÷');
});

test('dashboard rejects bad filter values', function () {
    $this->get(route('insights', ['range' => 'forever']))->assertSessionHasErrors('range');
});

test('the pdf report contains the stats, top quotes and interests', function () {
    $c = Category::create(['name' => 'Hope', 'name_ar' => 'أمل', 'slug' => 'hope']);
    $q = Quote::factory()->create(['content' => 'Top quote here', 'category_id' => $c->id]);
    User::factory()->create()->likedQuotes()->attach($q);

    $this->get(route('insights.report', ['range' => '30']))
        ->assertOk()
        ->assertSee('QuoteIt insights report')
        ->assertSee('Top quote here')
        ->assertSee('What users are interested in')
        ->assertSee('Download PDF');

    $this->withSession(['locale' => 'ar'])->get(route('insights.report'))->assertSee('تقرير بيانات QuoteIt')->assertSee('dir="rtl"', false);
});

// ---------- explanations and sharing ----------

test('quote that gets me explains why each quote was chosen', function () {
    $quote = Quote::factory()->create();
    $quote->goals()->attach(Goal::firstWhere('slug', 'hope'));
    $quote->moods()->attach(Mood::firstWhere('slug', 'sad'));

    $html = $this->actingAs(User::factory()->create())
        ->getJson(route('gets-me.suggest', ['mood' => 'sad', 'goal' => 'hope']))
        ->json('html');

    expect($html)->toContain('Why this quote?')->toContain('moments when you feel sad');
});

test('share links warn when the site runs on this computer only', function () {
    $quote = Quote::factory()->create();

    $this->get(route('quotes.show', $quote))->assertSee('this link only opens on this device');
});

test('like responses include new badges for the toast', function () {
    $user = User::factory()->create();
    $quote = Quote::factory()->create();

    $this->actingAs($user)->postJson(route('quotes.like', $quote))->assertOk()->assertJsonStructure(['active', 'count', 'badges']);
});
