<?php

namespace App\Exceptions;

use DomainException;

class CertificateException extends DomainException
{
    public static function notEligible(): self
    {
        return new self(__('certificates.validation.not_eligible'));
    }

    public static function alreadyIssued(): self
    {
        return new self(__('certificates.validation.already_issued'));
    }

    public static function alreadyRevoked(): self
    {
        return new self(__('certificates.messages.already_revoked'));
    }

    public static function notRevoked(): self
    {
        return new self(__('certificates.messages.not_revoked'));
    }
}
