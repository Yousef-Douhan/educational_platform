<?php

namespace App\Console\Commands;

use App\Services\LiveSessionService;
use Illuminate\Console\Command;

class CloseEndedLiveSessions extends Command
{
    protected $signature = 'live-sessions:close-ended';

    protected $description = 'Mark scheduled live sessions whose time has passed as ended';

    public function handle(LiveSessionService $sessions): int
    {
        $this->info('Closed ' . $sessions->closeEnded() . ' live session(s).');

        return self::SUCCESS;
    }
}
