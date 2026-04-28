<?php

namespace App\Exceptions\Domain;

use App\Exceptions\HttpApiException;

class ChargeProofAlreadySentException extends HttpApiException
{
    public static function make(string $message = 'Comprovante já enviado.', array $payload = []): self
    {
        return new self($message, 'PROOF_ALREADY_SENT', 422, $payload);
    }
}
