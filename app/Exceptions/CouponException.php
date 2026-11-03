<?php

namespace App\Exceptions;

use RuntimeException;

/** خطأ منطقي في استخدام الكوبون؛ الرمز يقابل مفتاحًا في coupons.errors */
class CouponException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message ?: $reason);
    }

    public static function because(string $reason): self
    {
        return new self($reason, __("coupons.errors.{$reason}"));
    }
}
