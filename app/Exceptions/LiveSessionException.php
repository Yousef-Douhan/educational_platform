<?php

namespace App\Exceptions;

use RuntimeException;

/** خطأ منطقي في الجلسات المباشرة؛ الرمز يقابل مفتاحًا في live_sessions.errors */
class LiveSessionException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message ?: $reason);
    }

    public static function because(string $reason): self
    {
        return new self($reason, __("live_sessions.errors.{$reason}"));
    }
}
