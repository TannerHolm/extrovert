<?php

namespace App\Http\Controllers\Influencers;

use App\Actions\Influencers\SaveInfluencerToList;
use App\Enums\CompensationType;
use App\Enums\DealStatus;
use App\Enums\IntegrationProvider;
use App\Enums\OutreachStatus;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Influencers\SaveInfluencerListRequest;
use App\Models\InfluencerList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InfluencerListController extends Controller
{
    /**
     * Maximum number of entries rendered on the kanban board in a single request.
     */
    private const KANBAN_ENTRY_LIMIT = 200;

    /**
     * Display all influencer lists for the current team.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('influencers/Lists', [
            'lists' => $team->influencerLists()
                ->withCount('entries')
                ->orderBy('name')
                ->get()
                ->map(fn (InfluencerList $list) => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'description' => $list->description,
                    'entries_count' => $list->entries_count,
                    'created_at' => $list->created_at->toISOString(),
                    'updated_at' => $list->updated_at->toISOString(),
                ]),
        ]);
    }

    /**
     * Store a new influencer list.
     */
    public function store(SaveInfluencerListRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );

        $list = $team->influencerLists()->create($request->safe()->only(['name', 'description']));

        // When an influencer is included (e.g. "save to a new list" from Discover), add it and
        // stay on the current page instead of navigating to the new list.
        if ($request->has('influencer')) {
            app(SaveInfluencerToList::class)->handle($list, $request->validated('influencer'), $request->user());

            return back();
        }

        return to_route('influencers.lists.show', [
            'current_team' => $team->slug,
            'influencerList' => $list->id,
        ]);
    }

    /**
     * Display a specific influencer list with its entries.
     */
    public function show(Request $request, InfluencerList $influencerList): Response
    {
        $team = $request->user()->currentTeam;

        abort_unless($influencerList->team_id === $team->id, 404);

        $status = $request->input('status');
        $view = $request->input('view', 'list');

        // Shop domain powers per-deal affiliate URLs; null when no store is connected.
        $shopDomain = $team->integrations()
            ->where('provider', IntegrationProvider::Shopify)
            ->first()
            ?->credentials['shop_domain'] ?? null;

        $mapEntry = fn ($entry) => [
            'id' => $entry->id,
            'outreach_status' => $entry->outreach_status->value,
            'outreach_status_label' => $entry->outreach_status->label(),
            'outreach_status_color' => $entry->outreach_status->color(),
            'notes' => $entry->notes,
            'added_by' => $entry->addedBy ? [
                'id' => $entry->addedBy->id,
                'name' => $entry->addedBy->name,
            ] : null,
            'created_at' => $entry->created_at->toISOString(),
            'influencer' => [
                'id' => $entry->influencer->id,
                'platform' => $entry->influencer->platform->value,
                'platform_label' => $entry->influencer->platform->label(),
                'handle' => $entry->influencer->handle,
                'profile_url' => $entry->influencer->profile_url,
                'display_name' => $entry->influencer->display_name,
                'avatar_url' => $entry->influencer->avatar_url,
                'follower_count' => $entry->influencer->follower_count,
                'engagement_rate' => $entry->influencer->engagement_rate,
                'contact_email' => $entry->influencer->contact_email,
                'latest_activity_at' => $entry->influencer->latest_activity_at?->toISOString(),
            ],
            'messages' => $entry->messages->map(fn ($message) => [
                'id' => $message->id,
                'direction' => $message->direction,
                'subject' => $message->subject,
                'body' => $message->body,
                'from_email' => $message->from_email,
                'to_email' => $message->to_email,
                'sent_by' => $message->user?->name,
                'sent_at' => $message->sent_at?->toISOString(),
            ])->all(),
            'deals' => $entry->deals->map(fn ($deal) => [
                'id' => $deal->id,
                'status' => $deal->status->value,
                'status_label' => $deal->status->label(),
                'status_color' => $deal->status->color(),
                'compensation_type' => $deal->compensation_type->value,
                'compensation_type_label' => $deal->compensation_type->label(),
                'flat_fee_cents' => $deal->flat_fee_cents,
                'commission_rate' => $deal->commission_rate !== null ? (float) $deal->commission_rate : null,
                'product_value_cents' => $deal->product_value_cents,
                'deliverables' => $deal->deliverables ?? [],
                'overdue_deliverables_count' => count($deal->overdueDeliverables()),
                'usage_rights' => $deal->usage_rights,
                'starts_at' => $deal->starts_at?->toDateString(),
                'ends_at' => $deal->ends_at?->toDateString(),
                'notes' => $deal->notes,
                'discount_code' => $deal->discount_code,
                'affiliate_url' => ($shopDomain && $deal->ref_token)
                    ? "https://{$shopDomain}/?ref={$deal->ref_token}&utm_source=extrovert&utm_content={$deal->ref_token}"
                    : null,
                'attribution' => [
                    'revenue_cents' => (int) ($deal->attributed_revenue_cents ?? 0),
                    'orders' => (int) ($deal->attributed_orders_count ?? 0),
                    'new_customers' => (int) ($deal->new_customers_count ?? 0),
                ],
                'agreements' => $deal->agreements->map(fn ($agreement) => [
                    'id' => $agreement->id,
                    'status' => $agreement->status->value,
                    'status_label' => $agreement->status->label(),
                    'status_color' => $agreement->status->color(),
                    'body_markdown' => $agreement->body_markdown,
                    'signer_name' => $agreement->signer_name,
                    'signer_email' => $agreement->signer_email,
                    'sent_at' => $agreement->sent_at?->toISOString(),
                    'signed_at' => $agreement->signed_at?->toISOString(),
                    'expires_at' => $agreement->expires_at?->toISOString(),
                    'sign_url' => route('sign.show', ['token' => $agreement->sign_token]),
                ])->all(),
                'created_at' => $deal->created_at->toISOString(),
            ])->all(),
        ];

        $query = $influencerList->entries()
            ->with(['influencer', 'addedBy', 'messages.user', 'deals' => fn ($deals) => $deals
                ->with('agreements')
                ->withSum('attributedOrders as attributed_revenue_cents', 'total_cents')
                ->withCount([
                    'attributedOrders as attributed_orders_count',
                    'attributedOrders as new_customers_count' => fn ($orders) => $orders->where('is_new_customer', true),
                ]),
            ])
            ->when($status && $status !== 'all', fn ($q) => $q->where('outreach_status', $status))
            ->orderByDesc('created_at');

        if ($view === 'kanban') {
            // Cap the kanban board to avoid loading an unbounded number of entries into memory.
            $entries = $query->limit(self::KANBAN_ENTRY_LIMIT)->get()->map($mapEntry)->values();
        } else {
            $paginator = $query->paginate(25)->through($mapEntry);

            // Shape the paginator as { data, links, meta } to match the frontend Paginator<T> type.
            $entries = [
                'data' => $paginator->items(),
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
            ];
        }

        return Inertia::render('influencers/ListShow', [
            'list' => [
                'id' => $influencerList->id,
                'name' => $influencerList->name,
                'description' => $influencerList->description,
            ],
            'entries' => $entries,
            'view' => $view,
            'filters' => ['status' => $status ?? 'all'],
            'outreachStatuses' => collect(OutreachStatus::cases())->map(fn (OutreachStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
            'dealStatuses' => collect(DealStatus::cases())->map(fn (DealStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
            'compensationTypes' => collect(CompensationType::cases())->map(fn (CompensationType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'canManage' => $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
        ]);
    }

    /**
     * Update an influencer list.
     */
    public function update(SaveInfluencerListRequest $request, InfluencerList $influencerList): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($influencerList->team_id === $team->id, 404);
        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );

        $influencerList->update($request->validated());

        return back();
    }

    /**
     * Delete an influencer list.
     */
    public function destroy(Request $request, InfluencerList $influencerList): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($influencerList->team_id === $team->id, 404);
        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );

        $influencerList->delete();

        return to_route('influencers.lists.index', ['current_team' => $team->slug]);
    }
}
