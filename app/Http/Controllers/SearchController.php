<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Quote;
use App\Services\SmartSearch;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SearchController extends Controller
{
    public function index(Request $request, SmartSearch $smartSearch)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'exists:categories,slug'],
            'sort' => ['nullable', 'in:relevance,'.implode(',', Quote::SORTS)],
        ]);
        $sort = $validated['sort'] ?? 'relevance';

        $query = trim($validated['q'] ?? '');
        $author = $validated['author'] ?? null;
        $category = isset($validated['category']) ? Category::firstWhere('slug', $validated['category']) : null;

        $results = null;
        $understood = null;
        if ($query !== '' || $author || $category) {
            ['results' => $all, 'understood' => $understood] = $smartSearch->search($query, $author, $category?->id, $sort);
            $page = LengthAwarePaginator::resolveCurrentPage();
            $results = new LengthAwarePaginator($all->forPage($page, 12)->values(), $all->count(), 12, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        return view('search.index', [
            'page' => 'Search',
            'query' => $query,
            'author' => $author,
            'activeCategory' => $category,
            'sort' => $sort,
            'results' => $results,
            'understood' => $understood,
            'authors' => Quote::whereNotNull('author')->where('author', '!=', '')->distinct()->orderBy('author')->limit(100)->pluck('author'),
            'examples' => [
                __('I want a quote about success after failure'),
                __('something to calm me down before an exam'),
                __('quotes about friendship'),
                'المتنبي',
            ],
        ]);
    }
}
