<?php

namespace App\Exceptions;

use RuntimeException;

/** خطأ منطقي في الطلبات؛ الرمز يقابل مفتاحًا في orders.errors */
class OrderException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message ?: $reason);
    }

    public static function because(string $reason): self
    {
        return new self($reason, __("orders.errors.{$reason}"));
    }
}
