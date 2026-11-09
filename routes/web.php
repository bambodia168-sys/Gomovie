<?php

use App\Http\Controllers\Account\LibraryController;
use App\Http\Controllers\Account\SettingsController;
use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EpisodeStudioController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PrivateIngestPreviewController;
use App\Http\Controllers\Admin\UploadStudioController;
use App\Http\Controllers\Admin\UploadStudioWorkflowController;
use App\Http\Controllers\Admin\UploadCaptionController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\PlayerSettingsController;
use App\Http\Controllers\Admin\TmdbController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Frontend\BrowseController;
use App\Http\Controllers\Frontend\PublicHlsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BrowseController::class, 'home'])->name('home');
Route::get('/browse', [BrowseController::class, 'index'])->name('browse');
Route::view('/about', 'frontend.about')->name('about');
Route::get('/watch/{type}/{slug}', [BrowseController::class, 'show'])->name('watch.show');
// Only integrity-verified FREE HLS assets; raw private ingests have no public route.
Route::get('/hls/{asset}/{path}', PublicHlsController::class)
    ->whereNumber('asset')->where('path', '.*')
    ->middleware('throttle:240,1')->name('public-hls.show');

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
    Route::middleware(['verified','throttle:30,1'])->group(function (): void {
        Route::get('/account', fn () => view('account.overview'))->name('account.overview');
        Route::get('/account/library/{section}',[LibraryController::class,'index'])->name('account.library');
        Route::post('/account/watchlist/toggle',[LibraryController::class,'toggle'])->name('account.watchlist.toggle');
        Route::get('/account/settings', [SettingsController::class,'index'])->name('account.settings');
        Route::patch('/account/settings/identity',[SettingsController::class,'updateIdentity'])->name('account.identity');
        Route::patch('/account/settings/password',[SettingsController::class,'changePassword'])
            ->middleware('throttle:5,1')->name('account.password');
        Route::post('/account/profiles',[SettingsController::class,'storeProfile'])->name('account.profiles.store');
        Route::delete('/account/profiles/{profileId}',[SettingsController::class,'removeProfile'])->name('account.profiles.destroy');
    });
});

// Standalone uploader: outside the dashboard layout, with the same staff and
// administrator access controls. Uploads remain private.
Route::middleware(['auth', 'active', 'verified', 'staff'])->group(function (): void {
    Route::get('/upload-studio', [UploadStudioController::class, 'index'])->name('upload-studio.index');
    Route::post('/upload-studio', [UploadStudioController::class, 'upload'])
        ->middleware('throttle:12,1')->name('upload-studio.upload');
    Route::get('/upload-studio/{ingest}/status', [UploadStudioWorkflowController::class, 'state'])
        ->whereNumber('ingest')->name('upload-studio.status');
    Route::patch('/upload-studio/{ingest}/details', [UploadStudioWorkflowController::class, 'details'])
        ->middleware('throttle:20,1')->whereNumber('ingest')->name('upload-studio.details');
    Route::post('/upload-studio/{ingest}/artwork', [UploadStudioWorkflowController::class, 'artwork'])
        ->middleware('throttle:12,1')->whereNumber('ingest')->name('upload-studio.artwork');
    // Caption management is limited to the uploader's own private ingest;
    // there is no unauthenticated subtitle or source-media route.
    Route::get('/upload-studio/{ingest}/captions', [UploadCaptionController::class, 'index'])
        ->whereNumber('ingest')->name('upload-studio.captions.index');
    Route::post('/upload-studio/{ingest}/captions', [UploadCaptionController::class, 'store'])
        ->middleware('throttle:12,1')->whereNumber('ingest')->name('upload-studio.captions.store');
    Route::patch('/upload-studio/{ingest}/captions/{caption}', [UploadCaptionController::class, 'update'])
        ->middleware('throttle:20,1')->whereNumber('ingest')->whereNumber('caption')->name('upload-studio.captions.update');
    Route::delete('/upload-studio/{ingest}/captions/{caption}', [UploadCaptionController::class, 'destroy'])
        ->middleware('throttle:20,1')->whereNumber('ingest')->whereNumber('caption')->name('upload-studio.captions.destroy');
    Route::get('/upload-studio/{ingest}/captions/{caption}/file', [UploadCaptionController::class, 'file'])
        ->middleware('throttle:120,1')->whereNumber('ingest')->whereNumber('caption')->name('upload-studio.captions.file');
    Route::patch('/upload-studio/{ingest}/access', [UploadStudioWorkflowController::class, 'access'])
        ->middleware('throttle:20,1')->whereNumber('ingest')->name('upload-studio.access');
});

Route::middleware(['auth', 'active', 'verified', 'staff'])->prefix('dashboard')->name('admin.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/catalog/{type}', [AdminCatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/{type}/create', [AdminCatalogController::class, 'create'])->name('catalog.create');
    Route::post('/catalog/{type}', [AdminCatalogController::class, 'store'])->name('catalog.store');
    Route::post('/catalog/{type}/bulk', [AdminCatalogController::class, 'bulk'])->name('catalog.bulk');
    Route::get('/catalog/{type}/{id}/preview', [AdminCatalogController::class, 'preview'])->name('catalog.preview');
    Route::post('/catalog/{type}/{id}/archive', [AdminCatalogController::class, 'archive'])->name('catalog.archive');
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
    Route::get('/upload-studio', fn () => redirect()->route('upload-studio.index'))
        ->name('upload-studio.legacy');
    // Old links remain navigable; the former approval page and POST actions
    // are removed. No video is approved or published by this redirect.
    Route::get('/upload-studio/{ingest}', function (\Illuminate\Http\Request $request, int $ingest) {
        abort_unless(in_array($request->user()?->role, ['admin', 'super_admin'], true), 403);
        return redirect()->route('admin.media.index');
    })->whereNumber('ingest')->name('upload-studio.legacy-review');
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    // Admin-only preview; never grants public playback, rights or stream entitlements.
    Route::get('/media/ingests/{ingest}/preview', PrivateIngestPreviewController::class)
        ->middleware('throttle:120,1')->whereNumber('ingest')->name('media.ingest-preview');
    Route::post('/media/artwork', [MediaController::class, 'artwork'])->name('media.artwork');
    Route::post('/media/ingests', [MediaController::class, 'ingest'])->name('media.ingest');
    Route::post('/tmdb/preview', [TmdbController::class, 'preview'])->name('tmdb.preview');
    Route::post('/tmdb/import', [TmdbController::class, 'import'])->name('tmdb.import');
    Route::get('/operations', [OperationsController::class, 'index'])->name('operations.index');
    Route::get('/player-settings', [PlayerSettingsController::class, 'edit'])->name('player-settings.edit');
    Route::patch('/player-settings', [PlayerSettingsController::class, 'update'])
        ->middleware('throttle:20,1')->name('player-settings.update');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
});
