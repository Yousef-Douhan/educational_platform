<?php

namespace App\Exceptions;

use RuntimeException;

/** خطأ منطقي في السلة؛ الرمز يقابل مفتاحًا في carts.errors */
class CartException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message ?: $reason);
    }

    public static function because(string $reason): self
    {
        return new self($reason, __("carts.errors.{$reason}"));
    }
}
