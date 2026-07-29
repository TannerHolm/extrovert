# Extrovert

Influencer-discovery + CRM tool. Teams discover creators across YouTube, Instagram, and
TikTok, save them to lists, and track outreach through a pipeline.

## Stack

- **Backend:** Laravel 13, PHP 8.4 (min 8.3). Auth via Laravel Fortify (2FA, email verification).
- **Frontend:** Inertia + Vue 3 + TypeScript (`strict: true`), Tailwind v4, Reka UI (shadcn-vue), Vite.
- **DB:** SQLite in tests (`:memory:`); configurable in dev/prod.

## Commands

- `composer dev` — runs server, queue, logs (pail), and Vite together.
- `composer setup` — first-time install (env, key, migrate, npm build).
- `composer lint` — Pint (PHP formatting). CI also runs `npm run lint` + `npm run format`.
- `npm run types:check` — `vue-tsc` type check. `npm run lint` — ESLint.
- Tests: `php artisan test` or `./vendor/bin/phpunit`. **Requires PHP ≥ 8.4** — if your
  default `php` is older, invoke an 8.4 binary explicitly.

## Architecture

### Multi-tenancy (important)
Every app route is nested under a `{current_team}` slug prefix and guarded by
`App\Http\Middleware\EnsureTeamMembership`. That middleware verifies membership, enforces an
optional minimum role, switches the user's current team, and **forgets the `current_team` route
parameter** so it doesn't shift positional controller-argument binding. Controllers derive the
team from `$request->user()->currentTeam` and scope every query to it, with explicit
`abort_unless($model->team_id === $team->id, 404)` IDOR checks. Path parameters use route-model
binding (e.g. `InfluencerList $influencerList`) — do **not** revert these to scalar `string`
params, or the team-prefix binding will misalign.

Roles/permissions: `App\Enums\TeamRole` (Owner > Admin > Member) → `App\Enums\TeamPermission`.
List/entry writes require `TeamPermission::ManageInfluencerLists` (Members are read-only).

### Influencer search
`App\Services\PlatformSearchManager` delegates to one service per platform
(`YouTubeSearchService`, `InstagramSearchService`, `TikTokSearchService`), all extending
`AbstractPlatformSearchService`, which provides a `final search()` that caches mapped results for
15 minutes and calls each subclass's `performSearch()`. Results are the readonly DTO
`App\Support\InfluencerSearchResult`. External APIs: YouTube Data API (`YOUTUBE_API_KEY`) and
RapidAPI scrapers for Instagram/TikTok (`RAPIDAPI_KEY` + host vars). Missing keys or upstream
failures throw `App\Exceptions\PlatformSearchException`, surfaced to the client as HTTP 503.
The `influencers/search/results` route is rate-limited (`throttle:30,1`) since it hits paid APIs.

### Lists & CRM
`InfluencerList` (team-scoped) → `InfluencerListEntry` (pivot to the shared `Influencer` table,
carrying `outreach_status`, `notes`, `added_by`). Outreach pipeline stages live in
`App\Enums\OutreachStatus`. `ListShow` supports a paginated list view and a kanban view (capped
at 200 entries/request). The dashboard aggregates per-status counts in one grouped query.

### Deals, attribution & agreements
`Deal` hangs off an entry (status/compensation/deliverables json; `DealStatus`,
`CompensationType`). Reaching `agreed` dispatches `ProvisionDealAttribution` (ref token +
Shopify discount code via `Services\Shopify\ShopifyClient`, credentials in `team_integrations`
with an `encrypted:array` cast). `POST /webhooks/shopify/{team}` verifies HMAC and queues
`ProcessShopifyOrder` → `Actions\Shopify\AttributeShopifyOrder` (discount code → ref link → UTM)
into `attributed_orders`; `extrovert:shopify-backfill {team}` imports history. Outbound outreach
sets `Reply-To: reply+<token>@INBOUND_MAIL_DOMAIN`; `POST /webhooks/inbound-email`
(token-authenticated) matches replies into the thread, advances `Contacted → Replied`, and parks
unmatched mail in `unmatched_inbound_emails`. Agreements: `DraftAgreement` (template merge +
optional Claude polish) → send snapshots a dompdf PDF with a sha256 `content_hash` → public
tokenized `routes/sign.php` page collects ESIGN consent + typed signature, re-verifies the hash,
logs `agreement_events`, and advances the deal. Cost math (product + fee + accrued commission)
lives in `Reports\PartnerRoiController` and `Services\Reports\CommissionReport` (monthly command
`extrovert:commission-report` + CSV route). Scheduled commands are registered in
`routes/console.php`.

### AI assist (suggestion queue)
`Services\AI\Claude` is the single Claude entry point (`services.anthropic.key`; `draft()` /
`draftJson()` return null when unconfigured — every caller has a template fallback).
Generators in `App\Jobs\Suggestions` write pending rows to `suggested_actions` (morph subject =
entry or deal): first-touch on save, `extrovert:suggest-follow-ups` for ghosted outreach, reply
triage on inbound (AI-only), deal recap on completion. `Outreach\SuggestionController` renders
the queue at `/{team}/suggestions`; approving fires the existing actions (send via
`SendOutreachEmail`, apply status, append recap to deal notes) — nothing sends unreviewed.

## Frontend layout
`resources/js/`: `pages/` (Inertia pages), `components/`, `composables/`, `layouts/`, `lib/`.
`actions/`, `routes/`, `wayfinder/` are generated by Wayfinder — don't hand-edit.

## Testing conventions
PHPUnit class style with `RefreshDatabase`. Factories exist for User, Team, Influencer,
InfluencerList, InfluencerListEntry. Fake external HTTP with `Http::fake()` and set
`config(['services.youtube.api_key' => ...])` / `services.rapidapi.key` in-test. Team-scoped
requests must pass `['current_team' => $user->currentTeam->slug, ...]` to `route()`.
