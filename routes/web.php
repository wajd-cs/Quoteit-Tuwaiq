<?php

use App\Http\Controllers\AccessibilityController;
use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\GetsMeController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\SignupController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;

// public pages
Route::get('/', [QuoteController::class, 'index'])->name('home');
Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
Route::get('/users/{user}', [ProfileController::class, 'show'])->name('users.show');
Route::get('/insights', [InsightsController::class, 'index'])->name('insights');
Route::get('/insights/export', [InsightsController::class, 'export'])->name('insights.export');
Route::get('/insights/report', [InsightsController::class, 'report'])->name('insights.report');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/quotes/{quote}/studio', [StudioController::class, 'show'])->name('studio');
Route::get('/users/{user}/avatar', [AvatarController::class, 'show'])->name('users.avatar');
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale');
Route::get('/accessibility', [AccessibilityController::class, 'index'])->name('accessibility');
Route::post('/quotes/{quote}/shared', [ShareController::class, 'store'])->middleware('throttle:60,1')->name('quotes.shared');
Route::put('/accessibility', [AccessibilityController::class, 'update'])->name('accessibility.update');
Route::get('/quotes/{quote}/translate', [TranslationController::class, 'show'])->middleware('throttle:30,1')->name('quotes.translate');

// guests only
Route::middleware('guest')->group(function () {
    Route::get('/signup', fn () => view('Authentication.SignUp', ['page' => 'Sign Up']))->name('signup');
    Route::post('/signup', [SignupController::class, 'signup']);

    Route::get('/login', fn () => view('Authentication.LogIn', ['page' => 'Log In']))->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// logged in users
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

    // quotes
    Route::post('/', [QuoteController::class, 'store'])->name('quotes.store');
    Route::put('/quote/{quote}', [QuoteController::class, 'update'])->name('edit_quote');
    Route::delete('/quote/{quote}', [QuoteController::class, 'destroy'])->name('delete_quote');
    Route::post('/quote/{quote}/publish-now', [QuoteController::class, 'publishNow'])->name('quotes.publish-now');

    // likes, favourites, comments, follows
    Route::post('/quotes/{quote}/like', [LikeController::class, 'toggle'])->name('quotes.like');
    Route::post('/quotes/{quote}/bookmark', [BookmarkController::class, 'toggle'])->name('quotes.bookmark');
    Route::get('/favorites', [BookmarkController::class, 'index'])->name('favorites');

    Route::post('/quotes/{quote}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::post('/users/{user}/follow', [FollowController::class, 'toggle'])->name('users.follow');

    // stories
    Route::post('/stories', [StoryController::class, 'store'])->name('stories.store');
    Route::post('/stories/{story}/like', [StoryController::class, 'like'])->name('stories.like');
    Route::delete('/stories/{story}', [StoryController::class, 'destroy'])->name('stories.destroy');

    // messages
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/with/{user}', [MessageController::class, 'start'])->name('messages.start');
    Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}', [MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/{conversation}/new', [MessageController::class, 'poll'])->name('messages.poll');

    // notifications
    Route::post('/notifications/seen', [NotificationController::class, 'seen'])->name('notifications.seen');
    Route::get('/live', [NotificationController::class, 'live'])->name('live');

    // quote that gets me
    Route::get('/gets-me', [GetsMeController::class, 'index'])->name('gets-me');
    Route::get('/gets-me/suggest', [GetsMeController::class, 'suggest'])->name('gets-me.suggest');
    Route::post('/gets-me/picks', [GetsMeController::class, 'pick'])->name('gets-me.pick');
    Route::get('/gets-me/history', [GetsMeController::class, 'history'])->name('gets-me.history');
    Route::delete('/gets-me/picks/{pick}', [GetsMeController::class, 'destroy'])->name('gets-me.destroy');

    // recommendations, achievements, studio
    Route::get('/recommendations', [RecommendationController::class, 'index'])->name('recommendations');
    Route::get('/achievements', [AchievementController::class, 'index'])->name('achievements');
    Route::post('/quotes/{quote}/designed', [StudioController::class, 'designed'])->name('studio.designed');

    // profile
    Route::get('/profile', [ProfileController::class, 'me'])->name('profile');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
});
