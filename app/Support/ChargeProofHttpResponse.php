<?php

namespace App\Support;

use App\Http\Responses\ApiResponse;
use App\Models\Charge;
use App\Services\PaymentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ChargeProofHttpResponse
{
    /**
     * Envia o comprovante mais recente da cobrança (sem expor path interno do storage).
     */
    public static function latest(Charge $charge, bool $inline): BinaryFileResponse|JsonResponse
    {
        $proof = $charge->latestProof();
        if (! $proof) {
            return ApiResponse::error(
                PaymentProofService::PROOF_NOT_FOUND_MESSAGE,
                PaymentProofService::PROOF_NOT_FOUND_CODE,
                404,
            );
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($proof->file_path)) {
            return ApiResponse::error(
                PaymentProofService::PROOF_NOT_FOUND_MESSAGE,
                PaymentProofService::PROOF_NOT_FOUND_CODE,
                404,
            );
        }

        $absolutePath = $disk->path($proof->file_path);
        $downloadName = SafeDownloadFilename::forProof((string) $proof->mime_type, $proof->original_filename);

        $response = response()->file($absolutePath, [
            'Content-Type' => $proof->mime_type ?: 'application/octet-stream',
        ]);

        return $response->setContentDisposition(
            $inline ? 'inline' : 'attachment',
            $downloadName,
            Str::ascii(str_replace('%', '', $downloadName)),
        );
    }
}
