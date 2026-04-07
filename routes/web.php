<?php

use App\Http\Controllers\Influencers\InfluencerListController;
use App\Http\Controllers\Influencers\InfluencerListEntryController;
use App\Http\Controllers\Influencers\InfluencerSearchController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        // Influencer Discovery
        Route::get('influencers/search', [InfluencerSearchController::class, 'index'])->name('influencers.search');
        Route::get('influencers/search/results', [InfluencerSearchController::class, 'search'])->name('influencers.search.results');

        // Influencer Lists
        Route::get('influencers/lists', [InfluencerListController::class, 'index'])->name('influencers.lists.index');
        Route::post('influencers/lists', [InfluencerListController::class, 'store'])->name('influencers.lists.store');
        Route::get('influencers/lists/{influencerList}', [InfluencerListController::class, 'show'])->name('influencers.lists.show');
        Route::patch('influencers/lists/{influencerList}', [InfluencerListController::class, 'update'])->name('influencers.lists.update');
        Route::delete('influencers/lists/{influencerList}', [InfluencerListController::class, 'destroy'])->name('influencers.lists.destroy');

        // Influencer List Entries
        Route::post('influencers/lists/{influencerList}/entries', [InfluencerListEntryController::class, 'store'])->name('influencers.entries.store');
        Route::patch('influencers/lists/{influencerList}/entries/{entry}', [InfluencerListEntryController::class, 'update'])->name('influencers.entries.update');
        Route::delete('influencers/lists/{influencerList}/entries/{entry}', [InfluencerListEntryController::class, 'destroy'])->name('influencers.entries.destroy');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
