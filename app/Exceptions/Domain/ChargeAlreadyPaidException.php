<?php

namespace App\Exceptions\Domain;

use App\Exceptions\HttpApiException;

class ChargeAlreadyPaidException extends HttpApiException
{
    public static function make(string $message = 'Pagamento já confirmado.', array $payload = []): self
    {
        return new self($message, 'PARTICIPANT_ALREADY_VALIDATED', 422, $payload);
    }
}
