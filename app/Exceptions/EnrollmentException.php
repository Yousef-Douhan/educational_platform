<?php

namespace App\Exceptions;

use RuntimeException;

/** خطأ منطقي في تسجيل الطالب؛ الرمز يقابل مفتاحًا في student.errors */
class EnrollmentException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message ?: $reason);
    }

    public static function because(string $reason): self
    {
        return new self($reason, __("student.errors.{$reason}"));
    }
}
