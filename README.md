# QuoteIt

Quote sharing website I built in the Tuwaiq Club bootcamp at Umm Al-Qura University, using Laravel 13, Blade and Tailwind CSS.

## Features

- Browse quotes as a guest, sign up / log in to share your own
- Share, edit and delete your quotes (with author and category)
- Like quotes and save them to favourites
- Search, filter by category, sort by latest or most liked
- Comments
- Profiles with bio, contact link, interests and stats
- Follow people + a "For you" feed based on your interests
- Quote of the day and similar quote suggestions
- Copy a quote or share it to X, WhatsApp, Telegram, LinkedIn and Facebook
- Read a quote out loud (Arabic / English) and change the text size
- **Quote That Gets Me (اقتباسي يفهمني)**: pick how you feel and what you need, get matching quotes, save them to favourites, and keep a private history of the quotes that helped
- **Arabic and English** on every page, right-to-left for Arabic (button in the top bar)
- **Quote design studio**: turn a quote into an image (templates, colours, fonts, sizes), save it as PNG or share it
- **Smart recommendations** that learn from your likes, saves and follows, with the reason for each one
- **Smart search**: write a sentence like "ابغى اقتباس عن النجاح بعد الفشل" and get matching quotes
- **Accessibility center**: high contrast, colour-blind modes, reading speed 0.75×–2× (also from the bar that appears while a quote is read aloud), text size, reduce motion, keyboard support; settings are saved automatically. Profile pictures.
- **Achievements**: daily quote challenge, badges, points, levels and a leaderboard
- **Insights dashboard**: KPIs, activity over the last 14 days, quotes and likes by category, activity by hour, language split, top quotes and contributors, a CSV export, date and topic filters, simple explanations of every indicator, and a printable **PDF report** in Arabic or English
- **Scheduled quotes**: write a quote now and pick when it goes live (Riyadh time). It shows up on its own at that time, no cron job needed
- **Light and dark mode** from the sun / moon button in the top bar (with a light Tuwaiq logo)
- **Your week**: a private summary on your profile of the likes, comments and shares your quotes got in the last 7 days
- **Sorting**: newest, oldest, most liked, most interactive, most shared, most commented (feed and search)

## Run locally

Needs PHP 8.4+, Composer and Node.js (or Laravel Herd).

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
composer run dev
```

Then open http://localhost:8000. Demo account: `demo@quoteit.test` / `password`

### Updating an existing copy

Copy the new files over your project folder (keep your `.env`, `vendor`, `node_modules` and `database/database.sqlite`), then:

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed     # resets the demo data; use `php artisan migrate` to keep your data
composer run dev
```

### Adding Quote That Gets Me to a database you already have

```bash
php artisan migrate                              # adds the new tables, keeps your data
php artisan db:seed --class=MoodGoalSeeder       # links goals to categories and tags the demo quotes
```

Then open http://localhost:8000/gets-me (you need to be logged in).

To use PostgreSQL (Supabase) instead of SQLite, change `DB_CONNECTION=pgsql` and the `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` values in `.env`, then run `php artisan migrate --seed`.

## Tests

```bash
php artisan test
```
