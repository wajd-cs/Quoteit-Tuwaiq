<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Goal;
use App\Models\Mood;
use App\Models\Quote;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo data so the site looks alive on first run.
     * Log in with demo@quoteit.test / password
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);
        $cat = Category::pluck('id', 'slug');

        // users are made without factories, so seeding also works on the live server (no Faker there)
        $makeUser = fn (string $name, string $email, ?string $bio, ?string $contact = null) => User::forceCreate([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'bio' => $bio,
            'contact_link' => $contact,
        ]);

        $demo = $makeUser('Demo User', 'demo@quoteit.test', 'Collecting words that make me think.');
        $demo->interests()->sync([$cat['motivation'], $cat['wisdom'], $cat['books']]);

        $people = collect([
            ['Sara Ahmed', 'Reader, writer, coffee lover.', 'https://x.com'],
            ['Noura Alqahtani', 'Software engineering student', 'https://linkedin.com'],
            ['Faisal Alharbi', 'I share what moves me.', null],
            ['Reem Alzahrani', 'Poetry is my second language.', 'reem@example.com'],
            ['Omar Khalid', 'Small steps every day.', null],
        ])->map(fn ($p) => $makeUser($p[0], Str::slug($p[0], '.').'@quoteit.test', $p[1], $p[2]));

        $quotes = [
            ['The secret of getting ahead is getting started.', 'Mark Twain', 'motivation'],
            ['It always seems impossible until it is done.', 'Nelson Mandela', 'motivation'],
            ['ما كل ما يتمنى المرء يدركه، تجري الرياح بما لا تشتهي السفن', 'المتنبي', 'wisdom'],
            ['The only true wisdom is in knowing you know nothing.', 'Socrates', 'wisdom'],
            ['Be yourself; everyone else is already taken.', 'Oscar Wilde', 'life'],
            ['من جدّ وجد، ومن زرع حصد', 'مثل عربي', 'success'],
            ['Success is not final, failure is not fatal: it is the courage to continue that counts.', null, 'success'],
            ['A room without books is like a body without a soul.', 'Cicero', 'books'],
            ['خير جليس في الزمان كتاب', 'المتنبي', 'books'],
            ['Love all, trust a few, do wrong to none.', 'William Shakespeare', 'love'],
            ['A friend to all is a friend to none.', 'Aristotle', 'friendship'],
            ['الصديق وقت الضيق', 'مثل عربي', 'friendship'],
            ['Life is really simple, but we insist on making it complicated.', 'Confucius', 'life'],
            ['In the middle of every difficulty lies opportunity.', null, 'motivation'],
            ['Small steps every day add up to big results.', null, 'motivation'],
            ['Write code for the next person who reads it.', null, 'wisdom'],
            ['إنّ مع العسر يسرا', 'القرآن الكريم', 'faith'],
            ['Do not wait to strike till the iron is hot; make it hot by striking.', 'W. B. Yeats', 'success'],
            ['I have not failed. I have just found 10,000 ways that won\'t work.', 'Thomas Edison', 'humor'],
            ['العلم في الصغر كالنقش على الحجر', 'مثل عربي', 'wisdom'],
            ['Where there is love there is life.', 'Mahatma Gandhi', 'love'],
            ['Good design is invisible until it is missing.', null, 'life'],
            ['This too shall pass.', 'Proverb', 'life'],
            ['ولربّ نازلةٍ يضيق بها الفتى ذرعًا، وعند الله منها المخرجُ', 'الإمام الشافعي', 'faith'],
            ['دع الأيام تفعل ما تشاء، وطب نفسًا إذا حكم القضاءُ', 'الإمام الشافعي', 'wisdom'],
            ['Believe you can and you\'re halfway there.', 'Theodore Roosevelt', 'success'],
            ['No one can make you feel inferior without your consent.', 'Eleanor Roosevelt', 'life'],
            ['Keep your face always toward the sunshine, and shadows will fall behind you.', 'Walt Whitman', 'life'],
            ['Nothing can bring you peace but yourself.', 'Ralph Waldo Emerson', 'wisdom'],
            ['Act as if what you do makes a difference. It does.', 'William James', 'motivation'],
            ['Happiness depends upon ourselves.', 'Aristotle', 'life'],
            ['إذا هبّت رياحك فاغتنمها، فإنّ لكلّ خافقةٍ سكونُ', 'شعر عربي', 'motivation'],
            ['Rest is not idleness.', 'John Lubbock', 'life'],
        ];

        $all = $people->push($demo);

        foreach ($quotes as $i => [$content, $author, $slug]) {
            $owner = $all[$i % $all->count()];
            $quote = new Quote(['content' => $content, 'author' => $author, 'category_id' => $cat[$slug]]);
            $quote->user()->associate($owner);
            // spread over the last two weeks, mostly in the evening
            $createdAt = now()->subDays(13 - intdiv($i * 14, count($quotes)))->setTime(rand(8, 23), rand(0, 59));
            $quote->created_at = $createdAt->isFuture() ? now()->subHours(rand(1, 4)) : $createdAt;
            $quote->published_at = $quote->created_at;
            $quote->save();

            $fans = $all->where('id', '!=', $owner->id)->shuffle()->take(($i * 3) % 6);
            foreach ($fans as $fan) {
                $likedAt = $quote->created_at->copy()->addHours(rand(1, 72));
                if ($likedAt->isFuture()) {
                    $likedAt = $quote->created_at->copy()->addMinutes(rand(5, 50));
                }
                $quote->likers()->attach($fan->id, ['created_at' => $likedAt, 'updated_at' => $likedAt]);
            }
            $quote->bookmarkers()->attach($fans->take(2)->pluck('id'));
        }

        $comments = ['This one hits different', 'Saving this for exam week!', 'جميل جدًا', 'So true.', 'Needed this today.'];
        Quote::inRandomOrder()->take(8)->get()->each(function ($quote, $i) use ($all, $comments) {
            $writer = $all->where('id', '!=', $quote->user_id)->random();
            $comment = $writer->comments()->make(['quote_id' => $quote->id, 'content' => $comments[$i % count($comments)]]);
            $comment->created_at = $quote->created_at->copy()->addMinutes(rand(10, 90));
            $comment->save();
        });

        $demo->following()->attach([$people[0]->id, $people[3]->id]);
        $people[0]->following()->attach([$demo->id, $people[1]->id]);
        $people[1]->following()->attach([$demo->id]);
        $people[2]->interests()->sync([$cat['life'], $cat['love']]);

        $this->call(MoodGoalSeeder::class);

        // a few "Quote That Gets Me" picks so the dashboard report has feelings to show
        $moods = Mood::pluck('id', 'slug');
        $goals = Goal::pluck('id', 'slug');
        $picks = [['sad', 'hope'], ['anxious', 'peace'], ['unmotivated', 'motivation'], ['sad', 'comfort'], ['excited', 'motivation'], ['anxious', 'confidence']];
        foreach ($all as $i => $user) {
            foreach (array_slice($picks, $i % 3, 2) as [$mood, $goal]) {
                $quote = Quote::whereHas('goals', fn ($q) => $q->where('slug', $goal))->inRandomOrder()->first();
                if ($quote) {
                    $user->quotePicks()->create(['quote_id' => $quote->id, 'mood_id' => $moods[$mood], 'goal_id' => $goals[$goal]]);
                }
            }
        }

        // one quote waiting to be published tomorrow evening (only the demo user sees it)
        $demo->quotes()->create([
            'content' => 'Every sunrise is a new chance to begin again.',
            'category_id' => $cat['hope'] ?? $cat['life'],
            'published_at' => now()->addDay()->setTime(21, 0),
        ]);

        // a few shares so "most shared" has data
        Quote::inRandomOrder()->take(6)->get()->each(function ($quote, $i) use ($all) {
            foreach (array_slice(['whatsapp', 'copy', 'x', 'image', 'telegram'], 0, ($i % 4) + 1) as $channel) {
                $quote->shares()->create(['user_id' => $all->random()->id, 'channel' => $channel]);
            }
        });

        // give everyone the badges they already earned from the demo data
        $achievements = app(AchievementService::class);
        $all->each(fn ($user) => $achievements->sync($user));
    }
}
