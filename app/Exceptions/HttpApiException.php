<?php

namespace App\Exceptions;

use Exception;

/**
 * Erro de API com código estável para clientes (mapeado para envelope ApiResponse).
 */
class HttpApiException extends Exception
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
