<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use Illuminate\Console\Command;

class ExpireEnrollments extends Command
{
    protected $signature = 'enrollments:expire';

    protected $description = 'Mark active enrollments past their expiry date as expired';

    public function handle(): int
    {
        $count = Enrollment::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} enrollment(s).");

        return self::SUCCESS;
    }
}
