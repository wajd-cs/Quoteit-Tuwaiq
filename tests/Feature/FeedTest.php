<?php

use App\Models\Category;
use App\Models\Quote;
use App\Models\User;

test('search finds quotes by text, author or sharer name', function () {
    Quote::factory()->create(['content' => 'The sea is calm', 'author' => null]);
    Quote::factory()->create(['content' => 'Different words', 'author' => 'Socrates']);
    Quote::factory()->create(['content' => 'Unrelated', 'author' => null]);

    $this->get('/?q=sea')->assertSee('The sea is calm')->assertDontSee('Unrelated');
    $this->get('/?q=socrates')->assertSee('Different words')->assertDontSee('Unrelated');
});

test('category filter shows only that category', function () {
    $love = Category::create(['name' => 'Love', 'slug' => 'love']);
    Quote::factory()->create(['content' => 'Love wins', 'category_id' => $love->id]);
    Quote::factory()->create(['content' => 'Something else']);

    $this->get('/?category=love')->assertSee('Love wins')->assertDontSee('Something else');
});

test('for you shows quotes from my interests and people I follow', function () {
    $books = Category::create(['name' => 'Books', 'slug' => 'books']);
    $me = User::factory()->create();
    $friend = User::factory()->create();
    $me->interests()->attach($books);
    $me->following()->attach($friend);

    Quote::factory()->create(['content' => 'A book quote', 'category_id' => $books->id]);
    Quote::factory()->for($friend)->create(['content' => 'From my friend']);
    Quote::factory()->create(['content' => 'Not for me']);

    $this->actingAs($me)->get('/?tab=for-you')
        ->assertOk()
        ->assertSee('A book quote')
        ->assertSee('From my friend')
        ->assertDontSee('Not for me');
});

test('for you asks guests to log in', function () {
    $this->get('/?tab=for-you')->assertRedirect(route('login'));
});

test('quote page suggests quotes with the same vibe', function () {
    $wisdom = Category::create(['name' => 'Wisdom', 'slug' => 'wisdom']);
    $quote = Quote::factory()->create(['category_id' => $wisdom->id]);
    Quote::factory()->create(['content' => 'Similar wisdom', 'category_id' => $wisdom->id]);

    $this->get(route('quotes.show', $quote))->assertSee('Same vibe')->assertSee('Similar wisdom');
});
