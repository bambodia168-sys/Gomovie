<?php

use Illuminate\Support\Facades\Route;
Route::prefix('v1')->group(function (): void {
    Route::get('status', fn () => response()->json(['project' => 'Gomovie', 'api' => 'v1', 'status' => 'development']));
});
