<?php

namespace App\Exceptions;

use RuntimeException;

/** خطأ منطقي في تقدم الطالب؛ الرمز يقابل مفتاحًا في progress.errors */
class ProgressException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message ?: $reason);
    }

    public static function because(string $reason): self
    {
        return new self($reason, __("progress.errors.{$reason}"));
    }
}
