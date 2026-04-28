<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message ?? 'Operação realizada com sucesso.',
            'data' => $data ?? new \stdClass,
            'meta' => $meta === [] ? new \stdClass : $meta,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function error(
        string $message,
        string $code = 'ERROR',
        int $status = 400,
        array $errors = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'errors' => $errors === [] ? new \stdClass : $errors,
        ], $status);
    }
}
