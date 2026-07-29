<?php

use App\Http\Controllers\Agreements\SignController;
use Illuminate\Support\Facades\Route;

// Public signing surface: unauthenticated-but-tokenized, same trust model as
// team invitations. The sign token is the signer's only credential.
Route::get('sign/{token}', [SignController::class, 'show'])->name('sign.show');
Route::post('sign/{token}/view', [SignController::class, 'viewed'])
    ->middleware('throttle:30,1')
    ->name('sign.viewed');
Route::post('sign/{token}', [SignController::class, 'sign'])
    ->middleware('throttle:10,1')
    ->name('sign.submit');
Route::post('sign/{token}/decline', [SignController::class, 'decline'])
    ->middleware('throttle:10,1')
    ->name('sign.decline');
