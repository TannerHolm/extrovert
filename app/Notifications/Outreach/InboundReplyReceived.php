<?php

namespace App\Notifications\Outreach;

use App\Models\OutreachMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class InboundReplyReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public OutreachMessage $message)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $entry = $this->message->entry;
        $influencer = $entry->influencer;
        $list = $entry->influencerList;
        $team = $list->team;
        $name = $influencer->display_name ?? $influencer->handle;

        return (new MailMessage)
            ->subject(__(':name replied to your outreach', ['name' => $name]))
            ->line(__(':name replied to your outreach on the ":list" list:', [
                'name' => $name,
                'list' => $list->name,
            ]))
            ->line('"'.Str::limit(trim($this->message->body), 300).'"')
            ->action(__('View thread'), url("/{$team->slug}/influencers/lists/{$list->id}"));
    }
}
