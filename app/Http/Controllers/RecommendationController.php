<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\RecommendationService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function index(Request $request, RecommendationService $recommendations)
    {
        $taste = $recommendations->tasteProfile($request->user());

        return view('recommendations.index', [
            'page' => 'Recommended',
            'items' => $recommendations->recommend($request->user(), 12),
            'taste' => $taste,
            'topCategories' => Category::whereIn('id', $taste['categories']->keys()->take(3))->get()
                ->sortBy(fn ($c) => array_search($c->id, $taste['categories']->keys()->all())),
        ]);
    }
}
