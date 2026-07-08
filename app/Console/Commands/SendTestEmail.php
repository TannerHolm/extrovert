<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTestEmail extends Command
{
    protected $signature = 'mail:test {email : Where to send the test message}';

    protected $description = 'Send a plain test email synchronously to verify the mail transport (bypasses the queue)';

    public function handle(): int
    {
        $to = $this->argument('email');

        $this->line('Mailer:  '.config('mail.default'));
        $this->line('From:    '.config('mail.from.address'));
        $this->line('Sending to: '.$to);

        try {
            Mail::raw('Extrovert test email sent at '.now()->toDateTimeString().'.', function ($message) use ($to) {
                $message->to($to)->subject('Extrovert test email');
            });
        } catch (Throwable $e) {
            $this->error('Send failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent via ['.config('mail.default')."]. Check the inbox (and your provider's logs).");

        return self::SUCCESS;
    }
}
