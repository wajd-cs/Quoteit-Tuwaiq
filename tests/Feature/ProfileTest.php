<?php

use App\Models\Category;
use App\Models\Quote;
use App\Models\User;

test('profile shows stats', function () {
    $user = User::factory()->create(['name' => 'Noura Ali']);
    $quote = Quote::factory()->for($user)->create();
    User::factory()->count(2)->create()->each(fn ($fan) => $fan->likedQuotes()->attach($quote));

    $response = $this->get(route('users.show', $user))->assertOk()->assertSee('Noura Ali');

    expect($response->viewData('stats'))->toMatchArray([
        'quotes' => 1,
        'likes_received' => 2,
        'followers' => 0,
    ]);
});

test('a user can update bio, contact link and interests', function () {
    $user = User::factory()->create();
    $category = Category::create(['name' => 'Faith', 'slug' => 'faith']);

    $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'New Name',
        'bio' => 'Hello!',
        'contact_link' => 'https://x.com/someone',
        'interests' => [$category->id],
    ])->assertRedirect(route('users.show', $user));

    $user->refresh();
    expect($user->name)->toBe('New Name')
        ->and($user->contactHref())->toBe('https://x.com/someone')
        ->and($user->interests)->toHaveCount(1);
});

test('contact link must be a web link or an email', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('profile.update'), ['name' => 'Test User', 'contact_link' => 'javascript:alert(1)'])
        ->assertSessionHasErrors('contact_link');
});
