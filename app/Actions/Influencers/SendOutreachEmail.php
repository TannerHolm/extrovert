<?php

namespace App\Actions\Influencers;

use App\Enums\OutreachStatus;
use App\Mail\OutreachEmail;
use App\Models\InfluencerListEntry;
use App\Models\OutreachMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendOutreachEmail
{
    /**
     * Send an outreach email to the entry's influencer and log it to the thread.
     *
     * The email is sent synchronously so a delivery failure surfaces to the caller
     * (and is not logged as sent). Replies route to the sending user until inbound
     * capture is wired up.
     */
    public function handle(InfluencerListEntry $entry, User $sender, string $subject, string $body): OutreachMessage
    {
        $toEmail = $entry->influencer->contact_email;
        $from = $entry->influencerList->team->sendingFrom();

        Mail::to($toEmail)->send(new OutreachEmail(
            subjectLine: $subject,
            bodyText: $body,
            fromEmail: $from['address'],
            replyToEmail: $sender->email,
            fromName: $from['name'] ?? '',
            replyToName: $sender->name,
        ));

        $message = $entry->messages()->create([
            'user_id' => $sender->id,
            'direction' => OutreachMessage::DIRECTION_OUTBOUND,
            'from_email' => $from['address'],
            'to_email' => $toEmail,
            'subject' => $subject,
            'body' => $body,
            'reply_token' => Str::random(40),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Sending outreach advances a fresh entry into the pipeline.
        if ($entry->outreach_status === OutreachStatus::None) {
            $entry->update(['outreach_status' => OutreachStatus::Contacted]);
        }

        return $message;
    }
}
