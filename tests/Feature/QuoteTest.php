<?php

use App\Models\Category;
use App\Models\Quote;
use App\Models\User;

test('guests can read quotes but not share them', function () {
    $quote = Quote::factory()->create(['content' => 'Stay curious.']);

    $this->get('/')->assertOk()->assertSee('Stay curious.')->assertSee('Log in to share a quote');
    $this->get(route('quotes.show', $quote))->assertOk()->assertSee('Stay curious.');
    $this->post('/', ['content' => 'Nope'])->assertRedirect(route('login'));
});

test('a user can share a quote with an author and category', function () {
    $user = User::factory()->create();
    $category = Category::create(['name' => 'Life', 'slug' => 'life']);

    $this->actingAs($user)->post('/', [
        'content' => 'Be yourself.',
        'author' => 'Oscar Wilde',
        'category_id' => $category->id,
    ])->assertRedirect();

    $quote = $user->quotes()->first();
    expect($quote->content)->toBe('Be yourself.')
        ->and($quote->author)->toBe('Oscar Wilde')
        ->and($quote->category_id)->toBe($category->id);
});

test('quote length must be between 3 and 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/', ['content' => 'Hi'])->assertSessionHasErrors('content');
    $this->actingAs($user)->post('/', ['content' => str_repeat('a', 256)])->assertSessionHasErrors('content');
    expect(Quote::count())->toBe(0);
});

test('only the owner can edit or delete a quote', function () {
    $quote = Quote::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->put(route('edit_quote', $quote), ['content' => 'Hacked!'])->assertForbidden();
    $this->actingAs($stranger)->delete(route('delete_quote', $quote))->assertForbidden();

    $this->actingAs($quote->user)->put(route('edit_quote', $quote), ['content' => 'Updated text'])->assertRedirect();
    expect($quote->fresh()->content)->toBe('Updated text');

    $this->actingAs($quote->user)->delete(route('delete_quote', $quote))->assertRedirect();
    expect(Quote::find($quote->id))->toBeNull();
});
