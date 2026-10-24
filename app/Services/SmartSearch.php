<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Goal;
use App\Models\Mood;
use App\Models\Quote;
use Illuminate\Support\Collection;

/**
 * Understands short sentences like "ابغى اقتباس عن النجاح بعد الفشل" or
 * "quotes about hope when I'm sad": removes filler words, maps words to topics
 * (categories, goals, moods) and ranks quotes by how well they match.
 *
 * It does not use an AI service: it uses a dictionary of topics (Arabic + English,
 * including Saudi everyday words), handles Arabic prefixes/endings and small typos.
 */
class SmartSearch
{
    private const STOPWORDS = [
        'اني', 'انني', 'حاس', 'حاسه', 'احس', 'حسيت', 'صرت', 'مره', 'شوي', 'كثير', 'عندي', 'احتاج',
        // Arabic (formal + everyday)
        'ابغى', 'ابغي', 'ابي', 'ابا', 'ابيك', 'ابغاك', 'اريد', 'اود', 'ودي', 'بغيت', 'عطني', 'اعطني', 'عطيني', 'هات', 'جيب', 'جيبلي', 'ابحث', 'دور', 'دورلي', 'لي', 'عن', 'في', 'من', 'على', 'الى', 'لين',
        'مع', 'او', 'و', 'ثم', 'هذا', 'هذه', 'هذي', 'ذا', 'شي', 'شيء', 'لو', 'ممكن', 'بس', 'يعني', 'اقتباس', 'اقتباسات', 'قول', 'اقوال', 'مقوله', 'كلام', 'كلمه', 'كلمات',
        'عباره', 'عبارات', 'جمله', 'بعد', 'قبل', 'لما', 'حين', 'عندما', 'اذا', 'انا', 'احس', 'حاس', 'حاسه', 'اشعر', 'كيف', 'وش', 'ايش', 'يكون', 'تكون', 'صار', 'صاير', 'عندي',
        'لكل', 'كل', 'ان', 'انه', 'التي', 'الذي', 'اللي', 'ما', 'لا', 'يا', 'جدا', 'مره', 'حلو', 'حلوه', 'شوي', 'شويه', 'نوع', 'موضوع', 'يتكلم', 'يحكي', 'تتكلم', 'عشان', 'علشان', 'لاني', 'لان',
        'اقتبس', 'يخليني', 'تخليني', 'يساعدني', 'يناسب', 'يناسبني', 'مناسب', 'حق', 'حقي', 'زي', 'مثل', 'كذا', 'مو', 'غير', 'جميل', 'جميله', 'افضل', 'احسن',
        // English
        'i', 'im', 'me', 'my', 'want', 'need', 'a', 'an', 'the', 'quote', 'quotes', 'about', 'after', 'before', 'for', 'some', 'something',
        'on', 'of', 'to', 'and', 'or', 'with', 'give', 'show', 'find', 'looking', 'is', 'that', 'when', 'feel', 'feeling', 'am', 'please',
        'saying', 'sayings', 'words', 'any', 'in', 'it', 'be', 'by', 'from', 'like', 'get', 'can', 'you', 'help', 'makes', 'make', 'nice', 'good', 'best', 'really', 'very', 'so',
    ];

    /**
     * topic => [words (normalised), categories, goals, moods]
     */
    private const TOPICS = [
        'success' => [['نجاح', 'نجح', 'ناجح', 'انجح', 'فوز', 'فاز', 'تفوق', 'متفوق', 'انجاز', 'انجازات', 'هدف', 'اهداف', 'وصول', 'قمه', 'تميز', 'success', 'succeed', 'successful', 'achieve', 'achievement', 'win', 'winning', 'goal', 'goals', 'excellence'], ['success'], ['motivation'], []],
        'failure' => [['فشل', 'فاشل', 'فشلت', 'خساره', 'خسرت', 'سقوط', 'طحت', 'خيبه', 'اخفاق', 'رسوب', 'رسبت', 'غلط', 'اغلاط', 'خطا', 'failure', 'fail', 'failed', 'lose', 'losing', 'lost', 'mistake', 'mistakes'], [], ['hope', 'comfort'], ['sad']],
        'hope' => [['امل', 'تفاول', 'متفائل', 'فرج', 'يسر', 'غد', 'بكره', 'نور', 'ضوء', 'hope', 'hopeful', 'optimism', 'optimistic', 'light', 'tomorrow'], [], ['hope'], []],
        'motivation' => [['تحفيز', 'يحمس', 'يحمسني', 'حمسني', 'دفعه', 'يحفز', 'حفز', 'محفز', 'حماس', 'دافع', 'همه', 'طموح', 'اجتهاد', 'جد', 'عزيمه', 'اراده', 'استمرار', 'motivation', 'motivate', 'motivated', 'drive', 'ambition', 'determination', 'hard', 'work', 'keep', 'going'], ['motivation'], ['motivation'], ['unmotivated']],
        'passion' => [['شغف', 'الشغف', 'رغبه', 'متعه', 'passion', 'passionate', 'spark'], ['motivation'], ['motivation'], ['unmotivated']],
        'laziness' => [['كسل', 'كسلان', 'تسويف', 'خمول', 'ملل', 'طفش', 'زهق', 'lazy', 'laziness', 'procrastinate', 'procrastination', 'bored', 'boring'], ['motivation'], ['motivation'], ['unmotivated']],
        'tired' => [['تعب', 'تعبان', 'تعبانه', 'مرهق', 'ارهاق', 'استنزاف', 'مستنزف', 'tired', 'exhausted', 'burnout', 'burned'], [], ['peace', 'comfort'], ['unmotivated']],
        'love' => [['حب', 'احب', 'عشق', 'محبه', 'قلب', 'غرام', 'حبيب', 'love', 'loving', 'heart', 'romance'], ['love'], [], []],
        'friendship' => [['صداقه', 'صديق', 'صديقه', 'اصدقاء', 'اصحاب', 'صاحب', 'رفيق', 'رفقه', 'خوي', 'friend', 'friends', 'friendship', 'buddy'], ['friendship'], [], []],
        'family' => [['عائله', 'اسره', 'اهل', 'ام', 'امي', 'والده', 'اب', 'ابوي', 'والد', 'اخ', 'اخت', 'ابن', 'بنت', 'family', 'mother', 'mom', 'father', 'dad', 'brother', 'sister', 'parents'], ['love', 'life'], ['comfort'], []],
        'faith' => [['ايمان', 'دين', 'الله', 'رب', 'دعاء', 'صلاه', 'قران', 'توكل', 'قدر', 'faith', 'god', 'allah', 'pray', 'prayer', 'religion'], ['faith'], ['comfort', 'peace'], []],
        'patience' => [['صبر', 'صابر', 'صبور', 'تحمل', 'انتظار', 'patience', 'patient', 'wait', 'waiting'], ['faith'], ['comfort', 'hope'], []],
        'gratitude' => [['شكر', 'امتنان', 'ممتن', 'حمد', 'نعمه', 'نعم', 'gratitude', 'grateful', 'thankful', 'thanks', 'blessing'], ['faith', 'life'], ['peace'], ['happy']],
        'sadness' => [['حزن', 'محبط', 'محبطه', 'احباط', 'انكسار', 'ضايق', 'متضايق', 'متضايقه', 'depressed', 'heartbroken', 'حزين', 'حزينه', 'ضيق', 'الم', 'وجع', 'هم', 'دموع', 'بكاء', 'كسره', 'مكسور', 'زعل', 'زعلان', 'sad', 'sadness', 'grief', 'pain', 'hurt', 'cry', 'broken', 'upset'], [], ['comfort'], ['sad']],
        'loss' => [['فقد', 'يرحم', 'يرحمه', 'متوفي', 'توفي', 'عزاء', 'فقدان', 'فراق', 'موت', 'رحيل', 'وداع', 'غياب', 'اشتياق', 'شوق', 'loss', 'goodbye', 'death', 'miss', 'missing', 'gone'], ['faith'], ['comfort', 'hope'], ['sad']],
        'loneliness' => [['وحده', 'وحيد', 'وحيده', 'عزله', 'لوحدي', 'lonely', 'alone', 'loneliness'], ['friendship'], ['comfort'], ['sad']],
        'anxiety' => [['قلق', 'قلقان', 'توتر', 'متوتر', 'خوف', 'خايف', 'ضغط', 'رهبه', 'هلع', 'anxious', 'anxiety', 'stress', 'stressed', 'fear', 'afraid', 'worry', 'worried', 'nervous', 'panic'], [], ['peace', 'confidence'], ['anxious']],
        'calm' => [['هدوء', 'يهدي', 'يهديني', 'اهدا', 'ارتاح', 'يريح', 'هادي', 'سكينه', 'راحه', 'سلام', 'طمانينه', 'استرخاء', 'روقان', 'calm', 'peace', 'peaceful', 'relax', 'rest', 'quiet', 'serenity'], [], ['peace'], ['calm']],
        'confidence' => [['ثقه', 'واثق', 'نفسي', 'ذات', 'ذاتي', 'شجاعه', 'جراه', 'قيمتي', 'confidence', 'confident', 'yourself', 'self', 'brave', 'courage', 'believe', 'worth'], [], ['confidence'], []],
        'strength' => [['قوه', 'عالق', 'stuck', 'قوي', 'صمود', 'تحدي', 'تحديات', 'صعوبه', 'صعوبات', 'صعب', 'شدائد', 'محنه', 'ازمه', 'strength', 'strong', 'difficult', 'difficulty', 'challenge', 'struggle', 'tough', 'resilience'], ['motivation'], ['hope', 'confidence'], []],
        'change' => [['تغيير', 'تغير', 'بدايه', 'ابدا', 'جديد', 'تطوير', 'تطور', 'نمو', 'change', 'start', 'begin', 'beginning', 'new', 'grow', 'growth'], ['motivation', 'life'], ['motivation'], []],
        'dreams' => [['حلم', 'احلام', 'امنيه', 'اماني', 'مستقبل', 'dream', 'dreams', 'wish', 'future'], ['motivation', 'success'], ['motivation', 'hope'], []],
        'work' => [['عمل', 'مقابله', 'وظيفي', 'وظيفيه', 'interview', 'شغل', 'وظيفه', 'دوام', 'مهنه', 'مشروع', 'شركه', 'work', 'job', 'career', 'business', 'project'], ['success', 'motivation'], ['motivation'], []],
        'study' => [['دراسه', 'ادرس', 'اختبار', 'اختبارات', 'امتحان', 'جامعه', 'مذاكره', 'ذاكر', 'طالب', 'طالبه', 'تخرج', 'study', 'studying', 'exam', 'exams', 'test', 'school', 'university', 'college', 'student'], ['success', 'motivation'], ['motivation', 'peace'], []],
        'life' => [['حياه', 'عمر', 'دنيا', 'ايام', 'عيش', 'life', 'living', 'live', 'world'], ['life'], [], []],
        'time' => [['وقت', 'زمن', 'لحظه', 'لحظات', 'ساعه', 'time', 'moment', 'moments'], ['wisdom', 'life'], [], []],
        'wisdom' => [['حكمه', 'حكم', 'عقل', 'علم', 'معرفه', 'تعلم', 'درس', 'دروس', 'wisdom', 'wise', 'knowledge', 'learn', 'learning', 'lesson'], ['wisdom'], [], []],
        'kindness' => [['لطف', 'طيبه', 'طيب', 'خير', 'احسان', 'رحمه', 'تسامح', 'عفو', 'مسامحه', 'kindness', 'kind', 'forgive', 'forgiveness', 'mercy', 'goodness'], ['wisdom', 'faith'], ['peace'], []],
        'books' => [['كتاب', 'كتب', 'قراءه', 'اقرا', 'قارئ', 'مكتبه', 'روايه', 'book', 'books', 'read', 'reading', 'reader', 'novel'], ['books'], [], []],
        'humor' => [['ضحك', 'اضحك', 'يضحك', 'ضحكه', 'يونس', 'فكاهه', 'مضحك', 'نكته', 'طقطقه', 'ونسه', 'funny', 'humor', 'humour', 'laugh', 'joke'], ['humor'], [], ['happy']],
        'happiness' => [['سعاده', 'سعيد', 'سعيده', 'فرح', 'فرحه', 'فرحان', 'مبسوط', 'وناسه', 'happy', 'happiness', 'joy', 'smile'], ['life'], [], ['happy']],
        'excitement' => [['حماسي', 'متحمس', 'متحمسه', 'excited', 'exciting', 'energy'], ['motivation'], ['motivation'], ['excited']],
        'morning' => [['صباح', 'صباحي', 'الصبح', 'يومي', 'morning', 'today'], ['motivation', 'life'], ['motivation'], []],
        'lost' => [['ضايع', 'ضايعه', 'ضياع', 'تايه', 'تايهه', 'محتار', 'محتاره', 'حيره', 'لخبطه', 'متلخبط', 'lost', 'confused', 'confusion', 'direction'], ['life', 'wisdom'], ['hope', 'peace'], ['sad']],
        'health' => [['صحه', 'مرض', 'مريض', 'شفاء', 'علاج', 'health', 'sick', 'healing', 'recovery'], ['faith'], ['hope', 'comfort'], []],
    ];

    private const PREFIXES = ['وال', 'بال', 'فال', 'كال', 'لل', 'ال'];

    private const SUFFIXES = ['ات', 'ين', 'ون', 'ان', 'ها', 'هم', 'كم', 'نا', 'ني', 'ه', 'ي', 'ك'];

    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text); // tashkeel + tatweel
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي', '’' => '', "'" => '']);

        return trim(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text));
    }

    /** Removes common Arabic prefixes (ال، وال، بال، لل، فال، كال، و) from a word. */
    public static function stem(string $word): string
    {
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($word, $prefix) && mb_strlen($word) - mb_strlen($prefix) >= 2) {
                return mb_substr($word, mb_strlen($prefix));
            }
        }
        if (str_starts_with($word, 'و') && mb_strlen($word) > 3) {
            return mb_substr($word, 1);
        }

        return $word;
    }

    /**
     * Possible base forms of a word: without prefixes, without endings (ات، ين، ها …),
     * and English plural / -ing / -ed forms.
     *
     * @return array<int, string>
     */
    public static function variants(string $word): array
    {
        $forms = [$word, self::stem($word)];

        foreach ([$word, self::stem($word)] as $base) {
            foreach (['ب', 'ل', 'ف', 'و'] as $letter) {
                if (str_starts_with($base, $letter) && mb_strlen($base) > 3) {
                    $forms[] = mb_substr($base, 1);
                }
            }
        }

        foreach ($forms as $form) {
            foreach (self::SUFFIXES as $suffix) {
                if (str_ends_with($form, $suffix) && mb_strlen($form) - mb_strlen($suffix) >= 3) {
                    $forms[] = mb_substr($form, 0, -mb_strlen($suffix));
                }
            }
            if (preg_match('/^[a-z]+$/', $form)) {
                foreach (['ing' => '', 'ed' => '', 'es' => '', 's' => '', 'ness' => ''] as $end => $replace) {
                    if (str_ends_with($form, $end) && strlen($form) - strlen($end) >= 3) {
                        $forms[] = substr($form, 0, -strlen($end)).$replace;
                    }
                }
            }
        }

        return array_values(array_unique($forms));
    }

    /** Edit distance that works with Arabic letters. */
    private static function distance(string $a, string $b): int
    {
        $a = mb_str_split($a);
        $b = mb_str_split($b);
        $prev = range(0, count($b));
        foreach ($a as $i => $ca) {
            $cur = [$i + 1];
            foreach ($b as $j => $cb) {
                $cur[] = min($prev[$j + 1] + 1, $cur[$j] + 1, $prev[$j] + ($ca === $cb ? 0 : 1));
            }
            $prev = $cur;
        }

        return end($prev);
    }

    /** Does a word from the search match a dictionary word? (same word, same root form, or one small typo) */
    private static function matches(array $forms, string $topicWord): bool
    {
        foreach ($forms as $form) {
            if ($form === $topicWord) {
                return true;
            }
            $len = mb_strlen($form);
            if ($len >= 4 && mb_strlen($topicWord) >= 3 && str_starts_with($form, $topicWord)) {
                return true;
            }
            if ($len >= 5 && abs($len - mb_strlen($topicWord)) <= 1 && self::distance($form, $topicWord) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{keywords: array<int, string>, topics: array<int, string>, categories: Collection, goals: Collection, moods: Collection}
     */
    public function understand(string $query): array
    {
        $words = preg_split('/\s+/u', self::normalize($query), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = [];
        foreach ($words as $word) {
            $stem = self::stem($word);
            if (! in_array($word, self::STOPWORDS, true) && ! in_array($stem, self::STOPWORDS, true) && mb_strlen($stem) >= 2) {
                $keywords[] = $stem;
            }
        }
        $keywords = array_values(array_unique($keywords));

        $topics = [];
        $categorySlugs = $goalSlugs = $moodSlugs = [];
        foreach ($keywords as $keyword) {
            $forms = self::variants($keyword);

            // an exact dictionary word wins; only guess (typo / longer word) when there is none
            $found = array_keys(array_filter(self::TOPICS, fn ($t) => array_intersect($forms, $t[0]) !== []));
            if ($found === []) {
                $found = array_keys(array_filter(self::TOPICS, function ($t) use ($forms) {
                    foreach ($t[0] as $topicWord) {
                        if (self::matches($forms, $topicWord)) {
                            return true;
                        }
                    }

                    return false;
                }));
            }

            foreach ($found as $topic) {
                [, $cats, $goals, $moods] = self::TOPICS[$topic];
                $topics[] = $topic;
                array_push($categorySlugs, ...$cats);
                array_push($goalSlugs, ...$goals);
                array_push($moodSlugs, ...$moods);
            }
        }

        return [
            'keywords' => $keywords,
            'topics' => array_values(array_unique($topics)),
            'categories' => Category::whereIn('slug', array_unique($categorySlugs))->get(),
            'goals' => Goal::whereIn('slug', array_unique($goalSlugs))->get(),
            'moods' => Mood::whereIn('slug', array_unique($moodSlugs))->get(),
        ];
    }

    /**
     * @return array{results: Collection, understood: array}
     */
    public function search(string $query, ?string $author = null, ?int $categoryId = null, string $sort = 'relevance'): array
    {
        $understood = $this->understand($query);
        $keywords = $understood['keywords'];
        $categoryIds = $understood['categories']->pluck('id')->all();
        $goalIds = $understood['goals']->pluck('id')->all();
        $moodIds = $understood['moods']->pluck('id')->all();

        // short root forms are used to find quotes; ranking uses the full forms below
        $likeTerms = collect($keywords)->flatMap(fn ($k) => self::variants($k))->filter(fn ($k) => mb_strlen($k) >= 3)->unique()->values()->all();

        $candidates = Quote::withCardData()
            ->with(['goals', 'moods'])
            ->withCount(['shares as shares_count', 'bookmarkers as saves_count'])
            ->when($author, fn ($q) => $q->where('author', $author))
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($likeTerms || $categoryIds || $goalIds || $moodIds, function ($q) use ($likeTerms, $categoryIds, $goalIds, $moodIds) {
                $q->where(function ($q) use ($likeTerms, $categoryIds, $goalIds, $moodIds) {
                    foreach ($likeTerms as $term) {
                        $q->orWhere('content', 'like', "%{$term}%")
                            ->orWhere('author', 'like', "%{$term}%")
                            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"));
                    }
                    if ($categoryIds) {
                        $q->orWhereIn('category_id', $categoryIds);
                    }
                    if ($goalIds) {
                        $q->orWhereHas('goals', fn ($g) => $g->whereIn('goals.id', $goalIds));
                    }
                    if ($moodIds) {
                        $q->orWhereHas('moods', fn ($m) => $m->whereIn('moods.id', $moodIds));
                    }
                });
            })
            ->limit(500)
            ->get();

        $results = $candidates->map(function (Quote $quote) use ($keywords, $categoryIds, $goalIds, $moodIds) {
            $text = self::normalize($quote->content);
            $textForms = collect(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY))->flatMap(fn ($w) => self::variants($w))->unique()->all();
            $authorText = self::normalize((string) $quote->author.' '.$quote->user->name);
            $score = 0;
            $matched = [];
            foreach ($keywords as $keyword) {
                $forms = self::variants($keyword);
                if ((mb_strlen($keyword) >= 3 && str_contains($text, $keyword)) || array_intersect($forms, $textForms)) {
                    $score += 3;
                    $matched[] = $keyword;
                } elseif (mb_strlen($keyword) >= 3 && str_contains($authorText, $keyword)) {
                    $score += 3;
                    $matched[] = $keyword;
                }
            }
            if ($quote->category_id && in_array($quote->category_id, $categoryIds, true)) {
                $score += 2;
            }
            $score += 2 * $quote->goals->whereIn('id', $goalIds)->count();
            $score += 1 * $quote->moods->whereIn('id', $moodIds)->count();
            $score += min($quote->likes_count, 10) * 0.05;

            return ['quote' => $quote, 'score' => $score, 'matched' => $matched];
        })
            ->filter(fn ($row) => $row['score'] > 0.5 || $keywords === [])
            ->values();

        $results = match ($sort) {
            'latest' => $results->sortByDesc(fn ($r) => $r['quote']->published_at),
            'oldest' => $results->sortBy(fn ($r) => $r['quote']->published_at),
            'popular' => $results->sortByDesc(fn ($r) => [$r['quote']->likes_count, $r['score']]),
            'commented' => $results->sortByDesc(fn ($r) => [$r['quote']->comments_count, $r['score']]),
            'shared' => $results->sortByDesc(fn ($r) => [$r['quote']->shares_count, $r['score']]),
            'interactive' => $results->sortByDesc(fn ($r) => [
                $r['quote']->likes_count + 2 * $r['quote']->comments_count + 2 * $r['quote']->saves_count + $r['quote']->shares_count,
                $r['score'],
            ]),
            default => $results->sortByDesc('score'),
        };

        return ['results' => $results->values(), 'understood' => $understood];
    }
}
