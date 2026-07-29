<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\DraftAgreement;
use App\Actions\Agreements\SendAgreement;
use App\Enums\AgreementStatus;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\Deal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AgreementController extends Controller
{
    /**
     * Draft an agreement for a deal from the team template + deal terms.
     */
    public function store(Request $request, Deal $deal): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        abort_unless($deal->team_id === $team->id, 404);
        $this->authorizeManage($request);

        $deal->agreements()->create([
            'team_id' => $team->id,
            'body_markdown' => app(DraftAgreement::class)->handle($deal),
            'signer_name' => $deal->entry->influencer->display_name ?? $deal->entry->influencer->handle,
            'signer_email' => $deal->entry->influencer->contact_email,
            'created_by' => $request->user()->id,
        ])->recordEvent('created', $request->ip(), $request->userAgent());

        return back();
    }

    /**
     * Update a draft's body or signer details. Sent agreements are immutable.
     */
    public function update(Request $request, Agreement $agreement): RedirectResponse
    {
        $this->authorizeAgreement($request, $agreement);

        abort_unless($agreement->status === AgreementStatus::Draft, 422);

        $agreement->update($request->validate([
            'body_markdown' => ['required', 'string', 'max:100000'],
            'signer_name' => ['required', 'string', 'max:255'],
            'signer_email' => ['required', 'email', 'max:255'],
        ]));

        return back();
    }

    /**
     * Send (or resend) the agreement for signature.
     */
    public function send(Request $request, Agreement $agreement): RedirectResponse
    {
        $this->authorizeAgreement($request, $agreement);

        abort_if(in_array($agreement->status, [AgreementStatus::Signed, AgreementStatus::Voided, AgreementStatus::Declined]), 422);

        if ($agreement->signer_email === null) {
            throw ValidationException::withMessages([
                'signer_email' => __('Add the signer\'s email address before sending.'),
            ]);
        }

        app(SendAgreement::class)->handle($agreement, $request->ip(), $request->userAgent());

        return back();
    }

    /**
     * Void the agreement — kills the signing token immediately.
     */
    public function void(Request $request, Agreement $agreement): RedirectResponse
    {
        $this->authorizeAgreement($request, $agreement);

        abort_if($agreement->status === AgreementStatus::Signed, 422);

        $agreement->update(['status' => AgreementStatus::Voided]);
        $agreement->recordEvent('voided', $request->ip(), $request->userAgent());

        return back();
    }

    private function authorizeAgreement(Request $request, Agreement $agreement): void
    {
        abort_unless($agreement->team_id === $request->user()->currentTeam->id, 404);
        $this->authorizeManage($request);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless(
            $request->user()->hasTeamPermission($request->user()->currentTeam, TeamPermission::ManageInfluencerLists),
            403,
        );
    }
}
