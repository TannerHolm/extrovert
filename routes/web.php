<?php

use App\Http\Controllers\Agreements\AgreementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Influencers\DealController;
use App\Http\Controllers\Influencers\InfluencerListController;
use App\Http\Controllers\Influencers\InfluencerListEntryController;
use App\Http\Controllers\Influencers\InfluencerSearchController;
use App\Http\Controllers\Influencers\OutreachEmailController;
use App\Http\Controllers\Reports\PartnerRoiController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Webhooks\InboundEmailWebhookController;
use App\Http\Controllers\Webhooks\ShopifyWebhookController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// Shopify order webhooks: unauthenticated, HMAC-verified per team. Registered
// before the {current_team} group so the prefix never captures "webhooks".
Route::post('webhooks/shopify/{team}', ShopifyWebhookController::class)
    ->name('webhooks.shopify');

// Inbound email from the mail provider: shared-token authenticated, throttled.
Route::post('webhooks/inbound-email', InboundEmailWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.inbound-email');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Influencer Discovery
        Route::get('influencers/search', [InfluencerSearchController::class, 'index'])->name('influencers.search');
        Route::get('influencers/search/results', [InfluencerSearchController::class, 'search'])
            ->middleware('throttle:30,1')
            ->name('influencers.search.results');

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

        // Outreach emails (send + logged thread)
        Route::post('influencers/lists/{influencerList}/entries/{entry}/emails', [OutreachEmailController::class, 'store'])->name('influencers.entries.emails.store');

        // Deals (negotiated terms, deliverables, compensation per entry)
        Route::post('influencers/lists/{influencerList}/entries/{entry}/deals', [DealController::class, 'store'])->name('influencers.entries.deals.store');
        Route::patch('influencers/lists/{influencerList}/entries/{entry}/deals/{deal}', [DealController::class, 'update'])->name('influencers.entries.deals.update');
        Route::delete('influencers/lists/{influencerList}/entries/{entry}/deals/{deal}', [DealController::class, 'destroy'])->name('influencers.entries.deals.destroy');

        // Reports (partner ROI + commission export)
        Route::get('reports/roi', [PartnerRoiController::class, 'index'])->name('reports.roi');
        Route::get('reports/commissions.csv', [PartnerRoiController::class, 'commissionsCsv'])->name('reports.commissions');

        // Agreements (drafted from deal terms, signed via tokenized public page)
        Route::post('deals/{deal}/agreements', [AgreementController::class, 'store'])->name('agreements.store');
        Route::patch('agreements/{agreement}', [AgreementController::class, 'update'])->name('agreements.update');
        Route::post('agreements/{agreement}/send', [AgreementController::class, 'send'])->name('agreements.send');
        Route::post('agreements/{agreement}/void', [AgreementController::class, 'void'])->name('agreements.void');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

require __DIR__.'/settings.php';
require __DIR__.'/sign.php';
