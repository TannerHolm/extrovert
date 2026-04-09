<?php

namespace App\Http\Controllers\Influencers;

use App\Enums\Platform;
use App\Exceptions\PlatformSearchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Influencers\SearchInfluencerRequest;
use App\Services\PlatformSearchManager;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InfluencerSearchController extends Controller
{
    /**
     * Display the influencer search page.
     */
    public function index(Request $request): Response
    {
        $team = Team::where('slug', $request->route('current_team'))->firstOrFail();

        return Inertia::render('influencers/Search', [
            'platforms' => collect(Platform::cases())->map(fn (Platform $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
            'lists' => $team->influencerLists()
                ->withCount('entries')
                ->orderBy('name')
                ->get()
                ->map(fn ($list) => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'entries_count' => $list->entries_count,
                ]),
        ]);
    }

    /**
     * Search for influencers on a platform.
     */
    public function search(SearchInfluencerRequest $request, PlatformSearchManager $manager): JsonResponse
    {
        $platform = Platform::from($request->validated('platform'));
        $query = $request->validated('query');
        $maxResults = $request->validated('max_results', 10);

        try {
            $results = $manager->search($platform, $query, $maxResults);
        } catch (PlatformSearchException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }

        return response()->json([
            'results' => collect($results)->map(fn ($r) => $r->toArray()),
        ]);
    }
}
