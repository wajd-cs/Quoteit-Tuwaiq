<?php

namespace App\Models;

use App\Support\QuoteStyle;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Fillable(['content', 'author', 'category_id', 'published_at', 'is_draft', 'style'])]
class Quote extends Model
{
    use HasFactory;

    public const SORTS = ['latest', 'oldest', 'popular', 'interactive', 'shared', 'commented'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'is_draft' => 'boolean', 'style' => 'array'];
    }

    protected static function booted(): void
    {
        // scheduled quotes stay hidden until their time comes; drafts have no time at all
        static::addGlobalScope('published', fn (Builder $q) => $q->where('quotes.published_at', '<=', now()));

        static::creating(function (Quote $quote) {
            if (! $quote->is_draft) {
                $quote->published_at ??= now();
            }
        });
    }

    /** Owners can open their scheduled quotes and drafts (edit, delete, publish now); everyone else gets 404. */
    public function resolveRouteBinding($value, $field = null)
    {
        $quote = static::withoutGlobalScope('published')->where($field ?? $this->getRouteKeyName(), $value)->first();

        if (! $quote || (($quote->isScheduled() || $quote->is_draft) && ! $quote->isOwnedBy())) {
            return null;
        }

        return $quote;
    }

    public function isScheduled(): bool
    {
        return ! $this->is_draft && $this->published_at !== null && $this->published_at->isFuture();
    }

    /** Not live yet: a draft or a scheduled quote. */
    public function isHidden(): bool
    {
        return $this->is_draft || $this->isScheduled();
    }

    /** My drafts, newest first. */
    public function scopeDraftsBy(Builder $query, User $user): Builder
    {
        return $query->withoutGlobalScope('published')
            ->where('user_id', $user->id)
            ->where('is_draft', true);
    }

    public function styleClasses(): string
    {
        return QuoteStyle::classes($this->style);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(QuoteTranslation::class);
    }

    /** Only my quotes that are waiting for their time. */
    public function scopeScheduledBy(Builder $query, User $user): Builder
    {
        return $query->withoutGlobalScope('published')
            ->where('user_id', $user->id)
            ->where('is_draft', false)
            ->where('quotes.published_at', '>', now());
    }

    public function shares(): HasMany
    {
        return $this->hasMany(QuoteShare::class);
    }

    /**
     * Sorting used on the home page and in search.
     * interactive = likes + comments x2 + saves x2 + shares (comments, saves and shares take more effort than a like).
     */
    public function scopeSortBy(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy('quotes.published_at')->orderBy('quotes.id'),
            'popular' => $query->withCount('likers as sort_likes')->orderByDesc('sort_likes')->orderByDesc('quotes.published_at'),
            'commented' => $query->withCount('comments as sort_comments')->orderByDesc('sort_comments')->orderByDesc('quotes.published_at'),
            'shared' => $query->withCount('shares as sort_shares')->orderByDesc('sort_shares')->orderByDesc('quotes.published_at'),
            'interactive' => $query
                ->when($query->getQuery()->columns === null, fn (Builder $q) => $q->select('quotes.*'))
                ->addSelect(DB::raw('((select count(*) from likes where likes.quote_id = quotes.id)'
                    .' + 2 * (select count(*) from comments where comments.quote_id = quotes.id)'
                    .' + 2 * (select count(*) from bookmarks where bookmarks.quote_id = quotes.id)'
                    .' + (select count(*) from quote_shares where quote_shares.quote_id = quotes.id)) as interaction_score'))
                ->orderByDesc('interaction_score')
                ->orderByDesc('quotes.published_at'),
            default => $query->orderByDesc('quotes.published_at')->orderByDesc('quotes.id'),
        };
    }

    public static function sortLabels(bool $withRelevance = false): array
    {
        return ($withRelevance ? ['relevance' => __('Best match')] : []) + [
            'latest' => __('Newest'),
            'oldest' => __('Oldest'),
            'popular' => __('Most liked'),
            'interactive' => __('Most interactive'),
            'shared' => __('Most shared'),
            'commented' => __('Most commented'),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'likes')->withTimestamps();
    }

    public function bookmarkers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bookmarks')->withTimestamps();
    }

    public function moods(): BelongsToMany
    {
        return $this->belongsToMany(Mood::class);
    }

    public function goals(): BelongsToMany
    {
        return $this->belongsToMany(Goal::class);
    }

    /**
     * Quote That Gets Me: quotes that fit a mood and a goal.
     * Tagged with the goal = 3 points, tagged with the mood = 2, category linked to the goal = 1.
     * Best matches come first, then random so the user gets different quotes each time.
     */
    public function scopeForMoodAndGoal(Builder $query, Mood $mood, Goal $goal): Builder
    {
        $categoryIds = $goal->categories()->pluck('categories.id')->all() ?: [0];
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        return $query
            ->withExists([
                'goals as goal_match' => fn ($q) => $q->where('goals.id', $goal->id),
                'moods as mood_match' => fn ($q) => $q->where('moods.id', $mood->id),
            ])
            ->where(function ($q) use ($mood, $goal, $categoryIds) {
                $q->whereHas('goals', fn ($g) => $g->where('goals.id', $goal->id))
                    ->orWhereHas('moods', fn ($m) => $m->where('moods.id', $mood->id))
                    ->orWhereIn('category_id', $categoryIds);
            })
            ->orderByRaw(
                '(case when exists (select 1 from goal_quote where goal_quote.quote_id = quotes.id and goal_quote.goal_id = ?) then 3 else 0 end)'
                .' + (case when exists (select 1 from mood_quote where mood_quote.quote_id = quotes.id and mood_quote.mood_id = ?) then 2 else 0 end)'
                ." + (case when quotes.category_id in ({$placeholders}) then 1 else 0 end) desc",
                [$goal->id, $mood->id, ...$categoryIds]
            )
            ->inRandomOrder();
    }

    public function scopeWithCardData(Builder $query): Builder
    {
        $query->with(['user', 'category'])->withCount(['likers as likes_count', 'comments']);

        if (Auth::check()) {
            $query->withExists([
                'likers as liked_by_me' => fn ($q) => $q->where('users.id', Auth::id()),
                'bookmarkers as bookmarked_by_me' => fn ($q) => $q->where('users.id', Auth::id()),
            ]);
        }

        return $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            // whereLike ignores upper/lower case on every database (PostgreSQL's LIKE does not)
            $q->whereLike('content', "%{$term}%")
                ->orWhereLike('author', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->whereLike('name', "%{$term}%"));
        });
    }

    public function isOwnedBy(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user !== null && $this->user_id === $user->id;
    }

    public function shareText(): string
    {
        $by = $this->author ?: $this->user->name;

        $isArabic = (bool) preg_match('/\p{Arabic}/u', $this->content);

        return $isArabic ? "«{$this->content}» — {$by}" : "“{$this->content}” — {$by}";
    }
}
