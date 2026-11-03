<?php

namespace App\Console\Commands;

use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class ListWebhooksNeedingReview extends Command
{
    protected $signature = 'webhooks:needs-review';

    protected $description = 'List verified gateway events that could not be applied (money may have been received)';

    public function handle(): int
    {
        $events = WebhookEvent::query()->where('status', 'failed')->orderBy('id')->get();

        if ($events->isEmpty()) {
            $this->info('No webhook events need review.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Gateway', 'Event', 'Type', 'Reason', 'Payment', 'Received'],
            $events->map(fn (WebhookEvent $e) => [
                $e->id, $e->gateway, $e->event_id, $e->type, $e->failure_reason, $e->payment_id ?? '—', $e->created_at->toDateTimeString(),
            ])->all()
        );

        return self::SUCCESS;
    }
}
