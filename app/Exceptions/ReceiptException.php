<?php

namespace App\Exceptions;

use RuntimeException;

class ReceiptException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message ?: $reason);
    }

    public static function because(string $reason): self
    {
        return new self($reason, __("receipts.errors.{$reason}"));
    }
}
