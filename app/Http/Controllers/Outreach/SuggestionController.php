<?php

namespace App\Http\Controllers\Outreach;

use App\Actions\Influencers\SendOutreachEmail;
use App\Enums\OutreachStatus;
use App\Enums\SuggestedActionStatus;
use App\Enums\SuggestedActionType;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\InfluencerListEntry;
use App\Models\SuggestedAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The approval queue: AI drafts land here, a human reviews (and can edit),
 * and approving fires the same actions the team would have taken by hand.
 */
class SuggestionController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        $suggestions = SuggestedAction::query()
            ->where('team_id', $team->id)
            ->where('status', SuggestedActionStatus::Pending)
            ->with('subject')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (SuggestedAction $suggestion) {
                $subject = $suggestion->subject;

                [$who, $where] = match (true) {
                    $subject instanceof InfluencerListEntry => [
                        $subject->influencer->display_name ?? $subject->influencer->handle,
                        $subject->influencerList->name,
                    ],
                    $subject instanceof Deal => [
                        $subject->entry->influencer->display_name ?? $subject->entry->influencer->handle,
                        'Deal #'.$subject->id,
                    ],
                    default => ['Unknown', ''],
                };

                return [
                    'id' => $suggestion->id,
                    'type' => $suggestion->type->value,
                    'type_label' => $suggestion->type->label(),
                    'sends_email' => $suggestion->type->sendsEmail(),
                    'payload' => $suggestion->payload,
                    'who' => $who,
                    'where' => $where,
                    'created_at' => $suggestion->created_at->toISOString(),
                ];
            });

        return Inertia::render('outreach/Suggestions', [
            'suggestions' => $suggestions,
            'canManage' => $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
        ]);
    }

    public function approve(Request $request, SuggestedAction $suggestion): RedirectResponse
    {
        $this->authorizeSuggestion($request, $suggestion);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
        ]);

        match ($suggestion->type) {
            SuggestedActionType::FirstTouch,
            SuggestedActionType::FollowUp => $this->sendDraft($request, $suggestion, $validated),
            SuggestedActionType::ReplyTriage => $this->applyTriage($request, $suggestion, $validated),
            SuggestedActionType::DealRecap => $this->saveRecap($suggestion),
        };

        $suggestion->update([
            'status' => SuggestedActionStatus::Approved,
            'actioned_by' => $request->user()->id,
            'actioned_at' => now(),
        ]);

        return back();
    }

    public function dismiss(Request $request, SuggestedAction $suggestion): RedirectResponse
    {
        $this->authorizeSuggestion($request, $suggestion);

        $suggestion->update([
            'status' => SuggestedActionStatus::Dismissed,
            'actioned_by' => $request->user()->id,
            'actioned_at' => now(),
        ]);

        return back();
    }

    /**
     * @param  array{subject?: string|null, body?: string|null}  $validated
     */
    private function sendDraft(Request $request, SuggestedAction $suggestion, array $validated): void
    {
        $entry = $suggestion->subject;

        abort_unless($entry instanceof InfluencerListEntry, 422);

        if ($entry->influencer->contact_email === null) {
            throw ValidationException::withMessages([
                'body' => __('This influencer no longer has a contact email.'),
            ]);
        }

        app(SendOutreachEmail::class)->handle(
            $entry,
            $request->user(),
            $validated['subject'] ?? $suggestion->payload['subject'],
            $validated['body'] ?? $suggestion->payload['body'],
        );
    }

    /**
     * Triage approval applies the suggested pipeline status, then sends the
     * drafted reply when one is present.
     *
     * @param  array{subject?: string|null, body?: string|null}  $validated
     */
    private function applyTriage(Request $request, SuggestedAction $suggestion, array $validated): void
    {
        $entry = $suggestion->subject;

        abort_unless($entry instanceof InfluencerListEntry, 422);

        $suggestedStatus = OutreachStatus::tryFrom((string) ($suggestion->payload['suggested_status'] ?? ''));

        if ($suggestedStatus !== null) {
            $entry->update(['outreach_status' => $suggestedStatus]);
        }

        $subject = $validated['subject'] ?? $suggestion->payload['subject'] ?? null;
        $body = $validated['body'] ?? $suggestion->payload['body'] ?? null;

        if ($subject && $body && $entry->influencer->contact_email !== null) {
            app(SendOutreachEmail::class)->handle($entry, $request->user(), $subject, $body);
        }
    }

    private function saveRecap(SuggestedAction $suggestion): void
    {
        $deal = $suggestion->subject;

        abort_unless($deal instanceof Deal, 422);

        $recap = (string) ($suggestion->payload['recap'] ?? '');
        $existing = trim((string) $deal->notes);

        $deal->update([
            'notes' => $existing === '' ? $recap : $existing."\n\n".$recap,
        ]);
    }

    private function authorizeSuggestion(Request $request, SuggestedAction $suggestion): void
    {
        $team = $request->user()->currentTeam;

        abort_unless($suggestion->team_id === $team->id, 404);
        abort_unless($suggestion->status === SuggestedActionStatus::Pending, 422);
        abort_unless(
            $request->user()->hasTeamPermission($team, TeamPermission::ManageInfluencerLists),
            403,
        );
    }
}
