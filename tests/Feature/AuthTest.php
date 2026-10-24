<?php

use App\Models\Category;
use App\Models\User;

test('a guest can sign up with interests and is logged in', function () {
    $category = Category::create(['name' => 'Wisdom', 'slug' => 'wisdom']);

    $this->post('/signup', [
        'name' => 'Wajd Ali',
        'email' => 'wajd@example.com',
        'password' => 'secret123',
        'interests' => [$category->id],
    ])->assertRedirect(route('home', ['tab' => 'for-you']));

    $user = User::firstWhere('email', 'wajd@example.com');
    $this->assertAuthenticatedAs($user);
    expect($user->interests->pluck('id')->all())->toBe([$category->id]);
});

test('sign up rejects a duplicate email and a name with numbers', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post('/signup', ['name' => 'Sara 2', 'email' => 'taken@example.com', 'password' => 'secret123'])
        ->assertSessionHasErrors(['name', 'email']);

    $this->assertGuest();
});

test('a user can log in and log out', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($user);

    $this->post('/logout')->assertRedirect(route('home'));
    $this->assertGuest();
});

test('wrong password shows an error', function () {
    $user = User::factory()->create();

    $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-pass'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});
