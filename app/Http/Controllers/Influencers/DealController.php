<?php

namespace App\Http\Controllers\Influencers;

use App\Enums\DealStatus;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Influencers\SaveDealRequest;
use App\Jobs\Shopify\ProvisionDealAttribution;
use App\Models\Deal;
use App\Models\InfluencerList;
use App\Models\InfluencerListEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DealController extends Controller
{
    /**
     * Create a deal for a list entry.
     */
    public function store(SaveDealRequest $request, InfluencerList $influencerList, InfluencerListEntry $entry): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        $this->authorizeEntry($request, $influencerList, $entry);

        $deal = $entry->deals()->create([
            ...$request->validated(),
            'team_id' => $team->id,
            'created_by' => $request->user()->id,
        ]);

        $this->provisionAttributionIfAgreed($deal);

        return back();
    }

    /**
     * Update a deal's terms or status.
     */
    public function update(SaveDealRequest $request, InfluencerList $influencerList, InfluencerListEntry $entry, Deal $deal): RedirectResponse
    {
        $this->authorizeEntry($request, $influencerList, $entry);
        abort_unless($deal->influencer_list_entry_id === $entry->id, 404);

        $deal->update($request->validated());

        $this->provisionAttributionIfAgreed($deal);

        return back();
    }

    /**
     * Delete a deal.
     */
    public function destroy(Request $request, InfluencerList $influencerList, InfluencerListEntry $entry, Deal $deal): RedirectResponse
    {
        $this->authorizeEntry($request, $influencerList, $entry);
        abort_unless($deal->influencer_list_entry_id === $entry->id, 404);

        $deal->delete();

        return back();
    }

    /**
     * Once a deal reaches agreed (or beyond), give it attribution handles —
     * a ref token plus a Shopify discount code when a store is connected.
     */
    private function provisionAttributionIfAgreed(Deal $deal): void
    {
        $needsProvisioning = in_array($deal->status, [DealStatus::Agreed, DealStatus::Live, DealStatus::Completed])
            && ($deal->ref_token === null || $deal->discount_code === null);

        if ($needsProvisioning) {
            ProvisionDealAttribution::dispatch($deal);
        }
    }

    /**
     * Verify team ownership of the list/entry chain and write permission.
     */
    private function authorizeEntry(Request $request, InfluencerList $influencerList, InfluencerListEntry $entry): void
    {
        $team = $request->user()->currentTeam;

        abort_unless($influencerList->team_id === $team->id, 404);
        abort_unless($entry->influencer_list_id === $influencerList->id, 404);
        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );
    }
}
