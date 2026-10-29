<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Goal;
use App\Models\Mood;
use App\Models\Quote;
use App\Models\Story;
use App\Models\User;
use App\Services\AchievementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * When this account exists, the live site already has the current demo data.
     * Change it when the demo data changes, so the next deploy swaps the old data out.
     */
    public const MARKER_EMAIL = 'lina.alotaibi@quoteit.test';

    /** [name, bio, contact link] */
    private const PEOPLE = [
        ['Lina Alotaibi', 'أحب الكلمات اللي تختصر شعور كامل.', null],
        ['Sara Ahmed', 'Reader, writer, coffee lover.', 'https://x.com'],
        ['Noura Alqahtani', 'Software engineering student', 'https://linkedin.com'],
        ['Faisal Alharbi', 'أشارك اللي يلمسني.', null],
        ['Reem Alzahrani', 'الشعر لغتي الثانية.', null],
        ['Omar Khalid', 'Small steps every day.', null],
        ['Abdullah Alshehri', 'مطوّر ويب، أحب البساطة.', 'https://github.com'],
        ['Hessa Almutairi', 'أقرأ كثير وأكتب قليل.', null],
        ['Yousef Aldosari', 'Data analyst. Numbers tell stories.', 'https://linkedin.com'],
        ['Maha Alghamdi', 'معلمة، وأحب أشارك طالباتي الحكم.', null],
        ['Khalid Alanazi', 'رياضة وكتب وقهوة.', null],
        ['Dana Alsubaie', 'UI designer. Less is more.', 'https://dribbble.com'],
        ['Turki Alsaud', 'أدوّن ما يستحق.', null],
        ['Ahmed Hassan', 'Engineer by day, reader by night.', null],
        ['Ghada Alqurashi', 'كل يوم اقتباس جديد.', null],
        ['Bader Alshammari', 'تويقي وأفتخر.', null],
        ['Jana Alrashid', 'Psychology student. Words heal.', null],
        ['Mohammed Alzahrani', 'أحب الأمثال الشعبية.', null],
        ['Rawan Alamri', 'مصورة، وأجمع الكلمات مثل الصور.', 'https://instagram.com'],
        ['Saud Alharthy', 'Product manager.', 'https://linkedin.com'],
        ['Layan Albalawi', 'طالبة طب، والاقتباسات تونسني وقت المذاكرة.', null],
        ['Fahad Alyami', 'أحب الشعر الجاهلي.', null],
        ['Nada Aljohani', 'Bookworm. Tea over coffee.', null],
        ['Majed Alenezi', 'مبرمج، وأؤمن إن الكود الحلو يُقرأ مثل الشعر.', 'https://github.com'],
        ['Shahad Alomari', 'كاتبة مبتدئة.', null],
        ['Ali Almalki', 'Runner. Early riser.', null],
        ['Wafa Alhazmi', 'أم وموظفة، وأحب الكلمات الهادية.', null],
        ['Hamad Alkhaldi', 'أشجع فريق تويق.', null],
        ['Ruba Alsulami', 'Learning something new every day.', null],
        ['Nawaf Alsharif', 'رحّال وقارئ.', null],
        ['Amal Alfaifi', 'أحب الحكمة القصيرة.', null],
        ['Ziyad Alqahtani', 'Cybersecurity student.', null],
        ['Haya Alnasser', 'تصميم وألوان وكلمات.', null],
        ['Meshal Alruwaili', 'أحب البساطة في كل شي.', null],
        ['Emily Carter', 'Exchange student in Riyadh. Learning Arabic!', null],
        ['Daniel Brooks', 'Developer. Coffee-powered.', 'https://github.com'],
        ['Hana Yamamoto', 'Fall seven times, stand up eight.', null],
        ['Leen Alhamdan', 'أكتب لنفسي أولًا.', null],
        ['Raed Alshahrani', 'مدرب لياقة، والكلمة الحلوة نصف التمرين.', null],
    ];

    private const COMMENTS = [
        'كلام من ذهب', 'جميل جدًا', 'وصلني في وقته', 'الله يسعدك على المشاركة', 'حفظته عندي',
        'صح والله', 'أحتاج أسمع هذا كل يوم', 'من أجمل ما قرأت اليوم', 'رهيب', 'بيت عظيم',
        'This one hits different', 'Saving this for exam week!', 'So true.', 'Needed this today.',
        'Beautiful.', 'Sending this to my sister', 'Love it', 'Exactly what I needed',
    ];

    private const MESSAGES = [
        ['Sara Ahmed', [
            ['them', 'Hi! I loved the quote you posted yesterday', 26],
            ['me', 'Thank you! It is one of my favourites', 25],
            ['them', 'Do you know any good books like it?', 2],
        ]],
        ['Lina Alotaibi', [
            ['them', 'هلا! شفت اقتباس المتنبي حقك، رهيب', 50],
            ['me', 'هلا لينا، الله يسعدك', 49],
            ['them', 'بكرة بنشر حالة جديدة، تابعيها', 48],
        ]],
        ['Abdullah Alshehri', [
            ['me', 'Thanks for following back!', 120],
            ['them', 'Anytime, great quotes on your profile', 119],
        ]],
    ];

    private const STORIES = [
        ['Lina Alotaibi', 'صباح الخير، اليوم بداية جديدة', null, 'alexandria', 24],
        ['Faisal Alharbi', 'على قدر أهل العزم تأتي العزائم', 'المتنبي', 'readex', 24],
        ['Noura Alqahtani', 'Exam week. Coffee, code, repeat.', null, 'rubik', 12],
        ['Reem Alzahrani', 'وخير جليس في الزمان كتاب', 'المتنبي', 'cairo', 24],
        ['Daniel Brooks', 'Ship it, then make it better.', null, 'rubik', 12],
        ['Hessa Almutairi', 'أقرأ الحين رواية جديدة، أنصحكم فيها', null, 'almarai', 24],
    ];

    public function run(): void
    {
        mt_srand(2026);

        $this->call(CategorySeeder::class);
        $categories = Category::pluck('id', 'slug');
        $now = CarbonImmutable::now();

        // users are made without factories, so seeding also works on the live server (no Faker there)
        $password = Hash::make('password');
        $makeUser = fn (string $name, string $email, ?string $bio, ?string $contact, CarbonImmutable $joined) => User::forceCreate([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $joined,
            'password' => $password,
            'bio' => $bio,
            'contact_link' => $contact,
            'created_at' => $joined,
            'updated_at' => $joined,
        ]);

        $demo = $makeUser('Demo User', 'demo@quoteit.test', 'Collecting words that make me think.', null, $now->subDays(70));
        $demo->forceFill(['notify_new_quotes' => true, 'notifications_seen_at' => $now->subDays(2)])->save();
        $demo->interests()->sync([$categories['motivation'], $categories['wisdom'], $categories['books']]);

        $people = collect(self::PEOPLE)->map(fn ($p) => $makeUser(
            $p[0], Str::slug($p[0], '.').'@quoteit.test', $p[1], $p[2], $now->subDays(mt_rand(62, 90))
        ));
        $byName = $people->keyBy('name');
        $everyone = $people->concat([$demo])->values();

        $slugs = $categories->keys()->all();
        $people->each(fn (User $user) => $user->interests()->sync(
            collect($slugs)->shuffle()->take(mt_rand(2, 4))->map(fn ($s) => $categories[$s])->all()
        ));

        $quoteIds = $this->seedQuotes($everyone, $demo, $categories, $now);
        $this->seedLikesAndBookmarks($quoteIds, $everyone, $now);
        $this->seedComments($quoteIds, $everyone);
        $this->seedFollows($everyone, $demo, $now);
        $this->seedShares($quoteIds, $everyone);

        $this->call(MoodGoalSeeder::class);
        $this->seedPicks($everyone);

        // one quote waiting to be published tomorrow evening, and one draft (only the demo user sees them)
        $demo->quotes()->create([
            'content' => 'Every sunrise is a new chance to begin again.',
            'category_id' => $categories['life'],
            'published_at' => $now->addDay()->setTime(21, 0),
        ]);
        $demo->quotes()->create([
            'content' => 'الأشياء الجميلة تحتاج وقت، مثل القهوة الجيدة والصداقات الحقيقية.',
            'category_id' => $categories['friendship'],
            'is_draft' => true,
            'published_at' => null,
        ]);

        self::seedStories();
        $this->seedMessages($demo, $byName, $now);

        // everyone gets the badges they already earned from the demo data
        $achievements = app(AchievementService::class);
        $everyone->each(fn (User $user) => $achievements->sync($user));
    }

    /**
     * Fresh stories from the demo accounts. Stories expire, so the live site calls this again
     * on start-up whenever none of them are left.
     */
    public static function seedStories(): void
    {
        foreach (self::STORIES as $i => [$name, $content, $author, $font, $hours]) {
            $user = User::where('email', Str::slug($name, '.').'@quoteit.test')->first();
            if (! $user) {
                continue;
            }
            $story = $user->stories()->create([
                'content' => $content,
                'author' => $author,
                'style' => ['font' => $font, 'align' => 'center'],
                'expires_at' => now()->addHours($hours)->subMinutes(30 + $i * 50),
            ]);
            $story->created_at = $story->expires_at->subHours($hours);
            $story->save();

            $likers = User::where('email', 'like', '%@quoteit.test')->whereKeyNot($user->id)->inRandomOrder()->take(2 + $i % 5)->pluck('id');
            $story->likers()->attach($likers);
        }
    }

    /**
     * @return Collection<int, array{id: int, user_id: int, at: CarbonImmutable, pull: int}>
     */
    private function seedQuotes(Collection $everyone, User $demo, Collection $categories, CarbonImmutable $now): Collection
    {
        $goals = Goal::pluck('id', 'slug');
        $moods = Mood::pluck('id', 'slug');
        $rows = collect(file(database_path('data/demo_quotes.tsv'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->reject(fn ($line) => str_starts_with($line, '#'))
            ->map(fn ($line) => explode("\t", $line))
            ->shuffle();

        // a few people post a lot, most post a little, like any real feed
        $weights = $everyone->mapWithKeys(fn (User $u, $i) => [$u->id => $u->is($demo) ? 6 : max(1, 14 - $i % 15)]);
        $pool = $weights->flatMap(fn ($w, $id) => array_fill(0, $w, $id))->values();

        $last = max(1, $rows->count() - 1);

        return $rows->map(function (array $row, int $i) use ($pool, $categories, $goals, $moods, $now, $last) {
            [$category, $goalSlugs, $moodSlugs, $author, $content] = $row;

            // spread over the last 60 days, busier in the evening
            $hour = mt_rand(1, 10) <= 6 ? mt_rand(18, 23) : mt_rand(7, 17);
            $at = $now->subDays(intdiv(59 * $i, $last))->setTime($hour, mt_rand(0, 59));
            if ($at->isFuture()) {
                $at = $now->subMinutes(mt_rand(20, 300));
            }

            $quote = new Quote([
                'content' => $content,
                'author' => $author === '-' ? null : $author,
                'category_id' => $categories[$category],
            ]);
            $quote->user_id = $pool[mt_rand(0, $pool->count() - 1)];
            $quote->created_at = $at;
            $quote->updated_at = $at;
            $quote->published_at = $at;
            $quote->save();

            $quote->goals()->attach(collect(explode(',', $goalSlugs))->map(fn ($s) => $goals[$s] ?? null)->filter()->all());
            $quote->moods()->attach(collect(explode(',', $moodSlugs))->map(fn ($s) => $moods[$s] ?? null)->filter()->all());

            // how far a quote travels: most get a handful of likes, a few take off
            $pull = (int) round(min(34, 2 + 32 * (mt_rand() / mt_getrandmax()) ** 3));

            return ['id' => $quote->id, 'user_id' => $quote->user_id, 'at' => $at, 'pull' => $pull];
        });
    }

    private function seedLikesAndBookmarks(Collection $quotes, Collection $everyone, CarbonImmutable $now): void
    {
        $likes = [];
        $bookmarks = [];
        foreach ($quotes as $quote) {
            $fans = $everyone->where('id', '!=', $quote['user_id'])->shuffle()->take($quote['pull']);
            foreach ($fans->values() as $n => $fan) {
                $at = $quote['at']->addMinutes(mt_rand(3, 60 * 24 * 3));
                $at = $at->isFuture() ? $now->subMinutes(mt_rand(1, 30)) : $at;
                $likes[] = ['user_id' => $fan->id, 'quote_id' => $quote['id'], 'created_at' => $at, 'updated_at' => $at];
                if ($n % 4 === 0) {
                    $bookmarks[] = ['user_id' => $fan->id, 'quote_id' => $quote['id'], 'created_at' => $at, 'updated_at' => $at];
                }
            }
        }
        foreach (array_chunk($likes, 500) as $chunk) {
            DB::table('likes')->insert($chunk);
        }
        foreach (array_chunk($bookmarks, 500) as $chunk) {
            DB::table('bookmarks')->insert($chunk);
        }
    }

    private function seedComments(Collection $quotes, Collection $everyone): void
    {
        $rows = [];
        foreach ($quotes->where('pull', '>=', 6)->values() as $i => $quote) {
            foreach (range(1, 1 + $i % 3) as $n) {
                $writer = $everyone->where('id', '!=', $quote['user_id'])->random();
                $at = $quote['at']->addMinutes(mt_rand(10, 600));
                $at = $at->isFuture() ? now()->subMinutes(mt_rand(1, 20)) : $at;
                $rows[] = [
                    'user_id' => $writer->id,
                    'quote_id' => $quote['id'],
                    'content' => self::COMMENTS[($i * 3 + $n) % count(self::COMMENTS)],
                    'created_at' => $at,
                    'updated_at' => $at,
                ];
            }
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('comments')->insert($chunk);
        }
    }

    private function seedFollows(Collection $everyone, User $demo, CarbonImmutable $now): void
    {
        $rows = [];
        foreach ($everyone as $i => $user) {
            $count = $user->is($demo) ? 9 : mt_rand(3, 14);
            foreach ($everyone->where('id', '!=', $user->id)->shuffle()->take($count) as $other) {
                $at = $now->subDays(mt_rand(1, 55));
                $rows[$user->id.'-'.$other->id] = ['follower_id' => $user->id, 'following_id' => $other->id, 'created_at' => $at, 'updated_at' => $at];
            }
        }
        // the demo account should feel followed too
        foreach ($everyone->where('id', '!=', $demo->id)->take(12) as $fan) {
            $rows[$fan->id.'-'.$demo->id] = ['follower_id' => $fan->id, 'following_id' => $demo->id, 'created_at' => $now->subDays(mt_rand(1, 40)), 'updated_at' => $now];
        }
        foreach (array_chunk(array_values($rows), 400) as $chunk) {
            DB::table('follows')->insert($chunk);
        }
    }

    private function seedShares(Collection $quotes, Collection $everyone): void
    {
        $channels = ['whatsapp', 'copy', 'x', 'image', 'telegram', 'whatsapp', 'instagram', 'snapchat', 'email', 'linkedin'];
        $rows = [];
        foreach ($quotes->where('pull', '>=', 4) as $quote) {
            foreach (range(1, max(1, intdiv($quote['pull'], 4))) as $n) {
                $rows[] = [
                    'quote_id' => $quote['id'],
                    'user_id' => $everyone->random()->id,
                    'channel' => $channels[mt_rand(0, count($channels) - 1)],
                    'created_at' => $quote['at']->addHours($n),
                    'updated_at' => $quote['at']->addHours($n),
                ];
            }
        }
        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('quote_shares')->insert($chunk);
        }
    }

    /** "Quote That Gets Me" picks so the dashboard has feelings to show. */
    private function seedPicks(Collection $everyone): void
    {
        $moods = Mood::pluck('id', 'slug');
        $goals = Goal::pluck('id', 'slug');
        $pairs = [['sad', 'hope'], ['anxious', 'peace'], ['unmotivated', 'motivation'], ['sad', 'comfort'], ['excited', 'motivation'], ['anxious', 'confidence'], ['calm', 'peace'], ['happy', 'comfort']];
        $byGoal = collect($goals)->map(fn ($id, $slug) => Quote::whereHas('goals', fn ($q) => $q->where('slug', $slug))->pluck('id'));

        $rows = [];
        foreach ($everyone as $i => $user) {
            foreach (range(1, 1 + $i % 4) as $n) {
                [$mood, $goal] = $pairs[($i + $n * 3) % count($pairs)];
                if ($byGoal[$goal]->isEmpty()) {
                    continue;
                }
                $at = now()->subDays(mt_rand(0, 30))->subMinutes(mt_rand(0, 600));
                $rows[] = ['user_id' => $user->id, 'quote_id' => $byGoal[$goal]->random(), 'mood_id' => $moods[$mood], 'goal_id' => $goals[$goal], 'created_at' => $at, 'updated_at' => $at];
            }
        }
        DB::table('quote_picks')->insert($rows);
    }

    private function seedMessages(User $demo, Collection $byName, CarbonImmutable $now): void
    {
        foreach (self::MESSAGES as [$name, $lines]) {
            $other = $byName[$name];
            $conversation = Conversation::between($demo, $other);
            foreach ($lines as $n => [$who, $body, $hoursAgo]) {
                $at = $now->subHours($hoursAgo);
                $isLast = $n === array_key_last($lines);
                $conversation->messages()->create([
                    'sender_id' => $who === 'me' ? $demo->id : $other->id,
                    'body' => $body,
                    // the newest message from Sara is left unread, so the badge shows
                    'read_at' => ($who === 'them' && $isLast && $name === 'Sara Ahmed') ? null : $at,
                ])->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
                $conversation->update(['last_message_at' => $at]);
            }
        }
    }
}
