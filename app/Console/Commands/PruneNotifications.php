<?php

namespace App\Console\Commands;

use App\Models\UserNotification;
use Illuminate\Console\Command;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Delete old read notifications (90 days) and very old unread ones (365 days)';

    public function handle(): int
    {
        $deleted = 0;

        do {
            // حذف على دفعات حتى لا يطول قفل الجدول
            $ids = UserNotification::query()->expired()->limit(1000)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : UserNotification::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === 1000);

        $this->info("Deleted {$deleted} notification(s).");

        return self::SUCCESS;
    }
}
