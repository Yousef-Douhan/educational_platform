<?php

namespace App\Console\Commands;

use App\Services\QuizAttemptService;
use Illuminate\Console\Command;

class ExpireQuizAttempts extends Command
{
    protected $signature = 'quizzes:expire-attempts';

    protected $description = 'Close and grade quiz attempts whose time limit has passed without submission';

    public function handle(QuizAttemptService $attempts): int
    {
        $count = $attempts->expireOverdue();

        $this->info("Closed {$count} overdue quiz attempt(s).");

        return self::SUCCESS;
    }
}
