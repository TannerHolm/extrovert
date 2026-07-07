<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateSendingDomainRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TeamSendingDomainController extends Controller
{
    /**
     * Update the team's outreach sending identity. Changing the address clears the
     * verified flag so the new domain's DNS must be confirmed again.
     */
    public function update(UpdateSendingDomainRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $email = $request->validated('sending_from_email');
        $unchanged = $team->sending_from_email === $email;

        $team->update([
            'sending_from_email' => $email,
            'sending_from_name' => $request->validated('sending_from_name'),
            'sending_domain_verified_at' => $unchanged ? $team->sending_domain_verified_at : null,
        ]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
