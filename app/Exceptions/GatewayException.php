<?php

namespace App\Exceptions;

use RuntimeException;

/** تعذّر التواصل مع بوابة الدفع. الرسالة للطالب عامة، والتفاصيل لا تُعرض له */
class GatewayException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message ?: $reason);
    }

    public static function because(string $reason): self
    {
        return new self($reason, __("student.pay.errors.{$reason}"));
    }
}
