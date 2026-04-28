<?php

namespace App\Exceptions\Domain;

use App\Exceptions\HttpApiException;

class InvalidChargeStateException extends HttpApiException
{
    public static function make(string $message, array $errors = []): self
    {
        return new self($message, 'INVALID_CHARGE_STATE', 422, $errors);
    }
}
