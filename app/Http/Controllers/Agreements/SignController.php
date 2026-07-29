<?php

namespace App\Http\Controllers\Agreements;

use App\Actions\Agreements\SignAgreement;
use App\Enums\AgreementStatus;
use App\Http\Controllers\Controller;
use App\Models\Agreement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public, tokenized signing surface. Unauthenticated by design — the
 * 64-char sign token is the signer's only credential, the same trust model
 * as team invitations.
 */
class SignController extends Controller
{
    public function show(string $token): Response
    {
        $agreement = $this->resolve($token);

        $signable = $agreement->status->isSignable() && ! $agreement->isExpired();

        // First open marks viewed (server-side render beats the JS beacon).
        if ($agreement->status === AgreementStatus::Sent && $signable) {
            $agreement->update(['status' => AgreementStatus::Viewed, 'viewed_at' => now()]);
            $agreement->recordEvent('viewed', request()->ip(), request()->userAgent());
        }

        return Inertia::render('sign/Show', [
            'agreement' => [
                'token' => $agreement->sign_token,
                'brand_name' => $agreement->team->name,
                'signer_name' => $agreement->signer_name,
                'signer_email' => $agreement->signer_email,
                'body_html' => Str::markdown($agreement->body_markdown),
                'status' => $agreement->status->value,
                'signable' => $signable,
                'expired' => $agreement->isExpired(),
                'signed_at' => $agreement->signed_at?->toISOString(),
                'expires_at' => $agreement->expires_at?->toISOString(),
                'content_hash' => $agreement->content_hash,
            ],
        ]);
    }

    /**
     * Beacon fired when the page is opened via client-side navigation.
     */
    public function viewed(string $token): JsonResponse
    {
        $agreement = $this->resolve($token);

        if ($agreement->status === AgreementStatus::Sent && ! $agreement->isExpired()) {
            $agreement->update(['status' => AgreementStatus::Viewed, 'viewed_at' => now()]);
            $agreement->recordEvent('viewed', request()->ip(), request()->userAgent());
        }

        return response()->json(['ok' => true]);
    }

    public function sign(Request $request, string $token): RedirectResponse
    {
        $agreement = $this->resolve($token);

        abort_unless($agreement->status->isSignable() && ! $agreement->isExpired(), 410);

        $validated = $request->validate([
            'typed_name' => ['required', 'string', 'max:255'],
            // ESIGN/UETA: consent to sign electronically must be explicit.
            'consent' => ['required', 'accepted'],
        ]);

        try {
            app(SignAgreement::class)->handle(
                $agreement,
                ['typed_name' => $validated['typed_name'], 'consent' => true],
                $request->ip(),
                $request->userAgent(),
            );
        } catch (\RuntimeException $exception) {
            abort(409, $exception->getMessage());
        }

        return back();
    }

    public function decline(Request $request, string $token): RedirectResponse
    {
        $agreement = $this->resolve($token);

        abort_unless($agreement->status->isSignable() && ! $agreement->isExpired(), 410);

        $agreement->update(['status' => AgreementStatus::Declined]);
        $agreement->recordEvent('declined', $request->ip(), $request->userAgent());

        return back();
    }

    private function resolve(string $token): Agreement
    {
        return Agreement::where('sign_token', $token)->with('team')->firstOrFail();
    }
}
