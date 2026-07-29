<?php

namespace App\Notifications\Deals;

use App\Models\Deal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class OverdueDeliverables extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $deliverables
     */
    public function __construct(public Deal $deal, public array $deliverables)
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
        $entry = $this->deal->entry;
        $influencer = $entry->influencer;
        $list = $entry->influencerList;
        $team = $this->deal->team;

        $message = (new MailMessage)
            ->subject(__(':name has overdue deliverables', [
                'name' => $influencer->display_name ?? $influencer->handle,
            ]))
            ->line(__('The following deliverables for your deal with :name are past due and have not been posted:', [
                'name' => $influencer->display_name ?? $influencer->handle,
            ]));

        foreach ($this->deliverables as $deliverable) {
            $message->line(__('- :type on :platform, due :date', [
                'type' => ucfirst($deliverable['type'] ?? 'deliverable'),
                'platform' => ucfirst($deliverable['platform'] ?? ''),
                'date' => Carbon::parse($deliverable['due_date'])->toFormattedDateString(),
            ]));
        }

        return $message->action(
            __('View list'),
            url("/{$team->slug}/influencers/lists/{$list->id}"),
        );
    }
}
