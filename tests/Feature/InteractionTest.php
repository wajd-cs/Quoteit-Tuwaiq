<?php

use App\Models\Quote;
use App\Models\User;

test('liking a quote toggles and returns the new count', function () {
    $user = User::factory()->create();
    $quote = Quote::factory()->create();

    $this->actingAs($user)->postJson(route('quotes.like', $quote))
        ->assertOk()->assertJson(['active' => true, 'count' => 1]);

    $this->actingAs($user)->postJson(route('quotes.like', $quote))
        ->assertOk()->assertJson(['active' => false, 'count' => 0]);
});

test('a user can save a quote and see it in favourites', function () {
    $user = User::factory()->create();
    $quote = Quote::factory()->create(['content' => 'Keep this one.']);

    $this->actingAs($user)->postJson(route('quotes.bookmark', $quote))->assertJson(['active' => true]);

    $this->actingAs($user)->get(route('users.show', [$user, 'tab' => 'saved']))
        ->assertOk()->assertSee('Keep this one.');
});

test('favourites are private to their owner', function () {
    $owner = User::factory()->create();
    $quote = Quote::factory()->create(['content' => 'Secret favourite']);
    $owner->bookmarkedQuotes()->attach($quote);

    $this->get(route('users.show', [$owner, 'tab' => 'saved']))->assertDontSee('Secret favourite');
});

test('guests cannot like, save, comment or follow', function () {
    $quote = Quote::factory()->create();

    $this->post(route('quotes.like', $quote))->assertRedirect(route('login'));
    $this->post(route('quotes.bookmark', $quote))->assertRedirect(route('login'));
    $this->post(route('comments.store', $quote), ['content' => 'Hi there'])->assertRedirect(route('login'));
    $this->post(route('users.follow', $quote->user))->assertRedirect(route('login'));
});

test('a user can comment and delete only their own comment', function () {
    $quote = Quote::factory()->create();
    $writer = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($writer)->post(route('comments.store', $quote), ['content' => 'Love this!'])->assertRedirect();
    $comment = $quote->comments()->first();
    expect($comment->content)->toBe('Love this!');

    $this->get(route('quotes.show', $quote))->assertSee('Love this!');

    $this->actingAs($stranger)->delete(route('comments.destroy', $comment))->assertForbidden();
    $this->actingAs($writer)->delete(route('comments.destroy', $comment))->assertRedirect();
    expect($quote->comments()->count())->toBe(0);
});

test('following toggles and a user cannot follow themselves', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($me)->postJson(route('users.follow', $other))->assertJson(['active' => true, 'count' => 1]);
    expect($me->isFollowing($other))->toBeTrue();

    $this->actingAs($me)->postJson(route('users.follow', $other))->assertJson(['active' => false]);
    $this->actingAs($me)->postJson(route('users.follow', $me))->assertStatus(422);
});
