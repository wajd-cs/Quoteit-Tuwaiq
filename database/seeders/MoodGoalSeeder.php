<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Goal;
use App\Models\Mood;
use App\Models\Quote;
use Illuminate\Database\Seeder;

/**
 * Data for "Quote That Gets Me". Safe to run on a database that already has data:
 * php artisan db:seed --class=MoodGoalSeeder
 */
class MoodGoalSeeder extends Seeder
{
    /** goal => categories that usually help with it */
    private const GOAL_CATEGORIES = [
        'motivation' => ['motivation', 'success'],
        'comfort' => ['faith', 'friendship', 'love'],
        'hope' => ['faith', 'life', 'motivation'],
        'peace' => ['wisdom', 'faith', 'life'],
        'confidence' => ['success', 'motivation', 'life'],
    ];

    /** quote text => [goals, moods] */
    private const TAGS = [
        'The secret of getting ahead is getting started.' => [['motivation'], ['unmotivated', 'excited']],
        'It always seems impossible until it is done.' => [['motivation', 'hope', 'confidence'], ['unmotivated', 'anxious']],
        'ما كل ما يتمنى المرء يدركه، تجري الرياح بما لا تشتهي السفن' => [['comfort', 'peace'], ['sad']],
        'Be yourself; everyone else is already taken.' => [['confidence'], ['anxious', 'happy']],
        'من جدّ وجد، ومن زرع حصد' => [['motivation'], ['unmotivated', 'excited']],
        'Success is not final, failure is not fatal: it is the courage to continue that counts.' => [['motivation', 'hope'], ['sad', 'unmotivated']],
        'الصديق وقت الضيق' => [['comfort'], ['sad']],
        'إنّ مع العسر يسرا' => [['hope', 'comfort'], ['sad', 'anxious']],
        'In the middle of every difficulty lies opportunity.' => [['hope'], ['anxious', 'sad']],
        'Small steps every day add up to big results.' => [['motivation'], ['unmotivated']],
        'Do not wait to strike till the iron is hot; make it hot by striking.' => [['motivation', 'confidence'], ['excited']],
        'Where there is love there is life.' => [['comfort'], ['happy', 'calm']],
        'Life is really simple, but we insist on making it complicated.' => [['peace'], ['anxious', 'calm']],
        'This too shall pass.' => [['comfort', 'hope'], ['sad', 'anxious']],
        'ولربّ نازلةٍ يضيق بها الفتى ذرعًا، وعند الله منها المخرجُ' => [['comfort', 'hope'], ['sad', 'anxious']],
        'دع الأيام تفعل ما تشاء، وطب نفسًا إذا حكم القضاءُ' => [['peace', 'comfort'], ['anxious', 'sad']],
        'Believe you can and you\'re halfway there.' => [['confidence', 'motivation'], ['unmotivated', 'anxious']],
        'No one can make you feel inferior without your consent.' => [['confidence'], ['sad', 'anxious']],
        'Keep your face always toward the sunshine, and shadows will fall behind you.' => [['hope'], ['sad', 'unmotivated']],
        'Nothing can bring you peace but yourself.' => [['peace'], ['anxious', 'calm']],
        'Act as if what you do makes a difference. It does.' => [['motivation', 'confidence'], ['unmotivated', 'excited']],
        'Happiness depends upon ourselves.' => [['peace', 'confidence'], ['happy', 'calm']],
        'إذا هبّت رياحك فاغتنمها، فإنّ لكلّ خافقةٍ سكونُ' => [['motivation'], ['excited', 'happy']],
        'Rest is not idleness.' => [['peace'], ['anxious', 'unmotivated']],
    ];

    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $goals = Goal::pluck('id', 'slug');
        $moods = Mood::pluck('id', 'slug');
        $categories = Category::pluck('id', 'slug');

        foreach (self::GOAL_CATEGORIES as $goal => $slugs) {
            Goal::find($goals[$goal])->categories()->syncWithoutDetaching(
                collect($slugs)->map(fn ($s) => $categories[$s] ?? null)->filter()->all()
            );
        }

        foreach (self::TAGS as $content => [$goalSlugs, $moodSlugs]) {
            Quote::where('content', $content)->get()->each(function (Quote $quote) use ($goals, $moods, $goalSlugs, $moodSlugs) {
                $quote->goals()->syncWithoutDetaching(collect($goalSlugs)->map(fn ($s) => $goals[$s])->all());
                $quote->moods()->syncWithoutDetaching(collect($moodSlugs)->map(fn ($s) => $moods[$s])->all());
            });
        }
    }
}
