<?php

use App\Models\Category;
use App\Models\Quote;
use App\Models\User;

test('insights page shows the main numbers', function () {
    $wisdom = Category::create(['name' => 'Wisdom', 'slug' => 'wisdom']);
    $quote = Quote::factory()->create(['category_id' => $wisdom->id, 'content' => 'العلم نور']);
    Quote::factory()->create(['content' => 'Knowledge is power']);
    User::factory()->create()->likedQuotes()->attach($quote);

    $response = $this->get(route('insights'))->assertOk()->assertSee('Insights dashboard');

    expect($response->viewData('kpis'))->toMatchArray(['quotes' => 2, 'likes' => 1, 'engagement' => 50])
        ->and($response->viewData('languages'))->toBe(['Arabic' => 1, 'English' => 1])
        ->and($response->viewData('trend'))->toHaveCount(14);
});

test('quotes can be downloaded as csv', function () {
    Quote::factory()->create(['content' => 'Export me']);

    $response = $this->get(route('insights.export'))->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Export me');
});
