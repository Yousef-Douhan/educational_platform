<?php

namespace App\Console\Commands;

use App\Services\ProgressService;
use Illuminate\Console\Command;

class RecalculateProgress extends Command
{
    protected $signature = 'progress:recalculate {--course= : Only recalculate this course id}';

    protected $description = 'Recalculate students\' course progress (after lessons were added, removed or unpublished)';

    public function handle(ProgressService $progress): int
    {
        $course = $this->option('course');
        $count  = $progress->recalculateAll(is_numeric($course) ? (int) $course : null);

        $this->info("Recalculated {$count} progress record(s).");

        return self::SUCCESS;
    }
}
