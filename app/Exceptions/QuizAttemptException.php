<?php

namespace App\Exceptions;

use RuntimeException;

/** خطأ منطقي في محاولات الاختبارات؛ الرمز يقابل مفتاحًا في quiz_attempts.errors */
class QuizAttemptException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message ?: $reason);
    }

    public static function because(string $reason): self
    {
        return new self($reason, __("quiz_attempts.errors.{$reason}"));
    }
}
