<?php

use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EpisodeStudioController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\TmdbController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Frontend\BrowseController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BrowseController::class, 'home'])->name('home');
Route::get('/browse', [BrowseController::class, 'index'])->name('browse');
Route::get('/watch/{type}/{slug}', [BrowseController::class, 'show'])->name('watch.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'loginForm'])->name('login');
    Route::post('/login', [SessionController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/register', [SessionController::class, 'registerForm'])->name('register');
    Route::post('/register', [SessionController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [SessionController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [SessionController::class, 'forgot'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [SessionController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [SessionController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

Route::post('/logout', [SessionController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/email/verify', [SessionController::class, 'verificationNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [SessionController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [SessionController::class, 'resend'])
        ->middleware('throttle:3,1')->name('verification.send');
    Route::get('/account', fn () => view('account.overview'))
        ->middleware('verified')->name('account.overview');
});

Route::middleware(['auth', 'active', 'verified', 'staff'])->prefix('dashboard')->name('admin.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/catalog/{type}', [AdminCatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/{type}/create', [AdminCatalogController::class, 'create'])->name('catalog.create');
    Route::post('/catalog/{type}', [AdminCatalogController::class, 'store'])->name('catalog.store');
    Route::get('/catalog/{type}/{id}/edit', [AdminCatalogController::class, 'edit'])->name('catalog.edit');
    Route::put('/catalog/{type}/{id}', [AdminCatalogController::class, 'update'])->name('catalog.update');
    Route::delete('/catalog/{type}/{id}', [AdminCatalogController::class, 'destroy'])->name('catalog.destroy');
    Route::get('/series/{series}/seasons', [EpisodeStudioController::class, 'index'])->name('seasons.index');
    Route::post('/series/{series}/seasons', [EpisodeStudioController::class, 'storeSeason'])->name('seasons.store');
    Route::patch('/series/{series}/seasons/{season}', [EpisodeStudioController::class, 'updateSeason'])->name('seasons.update');
    Route::delete('/series/{series}/seasons/{season}', [EpisodeStudioController::class, 'destroySeason'])->name('seasons.destroy');
    Route::post('/series/{series}/seasons/{season}/episodes', [EpisodeStudioController::class, 'storeEpisode'])->name('episodes.store');
    Route::patch('/series/{series}/seasons/{season}/episodes/{episode}', [EpisodeStudioController::class, 'updateEpisode'])->name('episodes.update');
    Route::delete('/series/{series}/seasons/{season}/episodes/{episode}', [EpisodeStudioController::class, 'destroyEpisode'])->name('episodes.destroy');
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::post('/media/artwork', [MediaController::class, 'artwork'])->name('media.artwork');
    Route::post('/media/ingests', [MediaController::class, 'ingest'])->name('media.ingest');
    Route::post('/tmdb/preview', [TmdbController::class, 'preview'])->name('tmdb.preview');
    Route::post('/tmdb/import', [TmdbController::class, 'import'])->name('tmdb.import');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
});
