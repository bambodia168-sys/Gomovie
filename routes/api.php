<?php

use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RatingController;
use App\Http\Controllers\Api\V1\StreamController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\WatchHistoryController;
use App\Http\Controllers\Api\V1\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:60,1')->group(function (): void {
        Route::get('/movies', [CatalogController::class, 'movies']);
        Route::get('/movies/{slug}', [CatalogController::class, 'movie']);
        Route::get('/series', [CatalogController::class, 'series']);
        Route::get('/series/{slug}', [CatalogController::class, 'showSeries']);
        Route::get('/series/{slug}/seasons/{number}', [CatalogController::class, 'season']);
    });

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('user')->group(function (): void {
        Route::get('/profiles', [ProfileController::class, 'index']);
        Route::post('/profiles', [ProfileController::class, 'store']);
        Route::delete('/profiles/{id}', [ProfileController::class, 'destroy']);
        Route::get('/watch-history', [WatchHistoryController::class, 'index']);
        Route::post('/watch-history/sync', [WatchHistoryController::class, 'syncProgress']);
        Route::get('/watchlist', [WatchlistController::class, 'index']);
        Route::post('/watchlist', [WatchlistController::class, 'store']);
        Route::delete('/watchlist/{id}', [WatchlistController::class, 'destroy']);
        Route::post('/ratings', [RatingController::class, 'store']);
        Route::get('/subscription', [SubscriptionController::class, 'show']);
        Route::post('/subscription/subscribe', [SubscriptionController::class, 'subscribe']);
    });

    Route::middleware(['auth:sanctum', 'throttle:20,1'])->group(function (): void {
        Route::post('/stream/movie/{id}/token', [StreamController::class, 'movie']);
        Route::post('/stream/episode/{id}/token', [StreamController::class, 'episode']);
        Route::post('/stream/session/{sessionId}/heartbeat', [StreamController::class, 'heartbeat']);
        Route::delete('/stream/session/{sessionId}', [StreamController::class, 'stop']);
    });
});
