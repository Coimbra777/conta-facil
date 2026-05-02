<?php

namespace App\Services;

use App\Exceptions\HttpApiException;
use App\Models\Charge;
use App\Models\Expense;
use App\Models\PaymentProof;
use App\Support\ChargeStatusTransition;
use App\Support\PhoneNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentProofService
{
    public const PROOF_NOT_FOUND_CODE = 'PROOF_NOT_FOUND';

    public const PROOF_NOT_FOUND_MESSAGE = 'Comprovante não encontrado.';

    public const PROOF_REMOVED_AFTER_EXPENSE_CLOSED_CODE = 'PROOF_REMOVED_AFTER_EXPENSE_CLOSED';

    public const PROOF_REMOVED_AFTER_EXPENSE_CLOSED_MESSAGE = 'Comprovantes excluídos após a finalização da cobrança.';

    public function uploadProof(Charge $charge, UploadedFile $file): PaymentProof
    {
        if (! in_array($charge->status, ['pending', 'rejected'], true)) {
            throw new HttpApiException(
                'Não é possível enviar comprovante neste estado.',
                'INVALID_CHARGE_STATE',
                422,
            );
        }

        if ($charge->paymentProofs()->exists() && $charge->status !== 'rejected') {
            throw new HttpApiException(
                'Comprovante ja enviado.',
                'PROOF_ALREADY_SENT',
                422,
            );
        }

        $previousStatus = $charge->status;
        $staleProofs = $previousStatus === 'rejected'
            ? $charge->paymentProofs()->get()
            : collect();

        $directory = $this->proofDirectoryForCharge($charge);
        $clientExt = strtolower((string) $file->getClientOriginalExtension());
        $ext = match ($clientExt) {
            'jpg', 'jpeg' => 'jpg',
            'png' => 'png',
            'pdf' => 'pdf',
            default => 'dat',
        };
        $storedName = $this->proofFilenameForCharge($charge, $ext);
        $path = $file->storeAs($directory, $storedName, 'local');

        if ($path === false) {
            throw new HttpApiException('Falha ao armazenar o arquivo.', 'UPLOAD_FAILED', 500);
        }

        $fullPath = Storage::disk('local')->path($path);
        $detectedMime = $this->detectAllowedMimeFromFile($fullPath);

        if ($detectedMime === null) {
            Storage::disk('local')->delete($path);
            throw new HttpApiException(
                'Tipo de arquivo não permitido.',
                'INVALID_FILE_TYPE',
                422,
            );
        }

        $safeOriginal = Str::limit(
            (string) preg_replace('/[^\pL\pN._ -]/u', '_', basename($file->getClientOriginalName())),
            200,
            '',
        );
        if ($safeOriginal === '') {
            $safeOriginal = 'comprovante';
        }

        $proof = PaymentProof::create([
            'charge_id' => $charge->id,
            'file_path' => $path,
            'original_filename' => $safeOriginal,
            'mime_type' => $detectedMime,
            'status' => 'pending',
        ]);

        if ($staleProofs->isNotEmpty()) {
            $charge->paymentProofs()->whereKey($staleProofs->modelKeys())->delete();
            $this->purgeProofFilesAfterCommit($staleProofs);
        }

        if ($previousStatus === 'rejected') {
            ChargeStatusTransition::assertTransition('rejected', 'pending');
            $charge->update(['status' => 'pending']);
        }

        return $proof;
    }

    public function getProofPath(PaymentProof $proof): string
    {
        if ($proof->file_path === null || $proof->file_path === '') {
            throw new HttpApiException(
                self::PROOF_NOT_FOUND_MESSAGE,
                self::PROOF_NOT_FOUND_CODE,
                404,
            );
        }

        return Storage::disk('local')->path($proof->file_path);
    }

    public function deleteProof(PaymentProof $proof): void
    {
        $proofs = new Collection([$proof]);

        $proof->delete();

        $this->purgeProofFilesAfterCommit($proofs);
    }

    public function deleteProofsForCharge(Charge $charge): void
    {
        $proofs = $charge->paymentProofs()->get();

        if ($proofs->isEmpty()) {
            return;
        }

        $charge->paymentProofs()->whereKey($proofs->modelKeys())->delete();

        $this->purgeProofFilesAfterCommit($proofs);
    }

    public function removeProofFilesForExpense(Expense $expense): void
    {
        $proofs = PaymentProof::query()
            ->whereHas('charge', fn ($query) => $query->where('expense_id', $expense->id))
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->get();

        if ($proofs->isEmpty()) {
            return;
        }

        PaymentProof::query()
            ->whereKey($proofs->modelKeys())
            ->update([
                'file_path' => null,
            ]);

        $this->purgeProofFilesAfterCommit($proofs);
    }

    public function assertProofAccessible(Charge $charge): void
    {
        $expense = $charge->expense;

        if ($expense?->status === 'closed') {
            throw new HttpApiException(
                self::PROOF_REMOVED_AFTER_EXPENSE_CLOSED_MESSAGE,
                self::PROOF_REMOVED_AFTER_EXPENSE_CLOSED_CODE,
                404,
            );
        }
    }

    private function purgeProofFilesAfterCommit(Collection $proofs): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->purgeProofFiles($proofs));

            return;
        }

        $this->purgeProofFiles($proofs);
    }

    private function purgeProofFiles(Collection $proofs): void
    {
        $disk = Storage::disk('local');
        $directories = [];

        foreach ($proofs as $proof) {
            if (! $proof instanceof PaymentProof || $proof->file_path === null || $proof->file_path === '') {
                continue;
            }

            $directory = dirname($proof->file_path);
            if ($directory !== '.' && $directory !== '') {
                $directories[$directory] = true;
            }

            try {
                if (! $disk->exists($proof->file_path)) {
                    continue;
                }

                if (! $disk->delete($proof->file_path)) {
                    Log::warning('Failed to delete payment proof file.', [
                        'payment_proof_id' => $proof->id,
                        'charge_id' => $proof->charge_id,
                        'file_path' => $proof->file_path,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to delete payment proof file.', [
                    'payment_proof_id' => $proof->id,
                    'charge_id' => $proof->charge_id,
                    'file_path' => $proof->file_path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach (array_keys($directories) as $directory) {
            try {
                if ($disk->exists($directory) && $disk->allFiles($directory) === []) {
                    $disk->deleteDirectory($directory);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to delete payment proof directory.', [
                    'directory' => $directory,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function proofDirectoryForCharge(Charge $charge): string
    {
        return 'payment-proofs/expense-'.$charge->expense_id;
    }

    private function proofFilenameForCharge(Charge $charge, string $extension): string
    {
        $phone = $this->normalizedParticipantPhoneForCharge($charge);
        $timestamp = now()->format('Ymd-Hisv');

        return $phone.'-'.$timestamp.'.'.$extension;
    }

    private function normalizedParticipantPhoneForCharge(Charge $charge): string
    {
        $charge->loadMissing('expenseParticipant');

        $phone = PhoneNormalizer::digits((string) ($charge->expenseParticipant?->phone_normalized
            ?? $charge->expenseParticipant?->phone
            ?? ''));

        if ($phone === '') {
            throw new HttpApiException(
                'Participante sem telefone válido.',
                'INVALID_PARTICIPANT_PHONE',
                422,
            );
        }

        return $phone;
    }

    /**
     * Valida tipo real pelo conteúdo (magic bytes), sem confiar só no MIME declarado.
     */
    private function detectAllowedMimeFromFile(string $absolutePath): ?string
    {
        $head = @file_get_contents($absolutePath, false, null, 0, 12);
        if ($head === false || $head === '') {
            return null;
        }

        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($head, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($head, '%PDF')) {
            return 'application/pdf';
        }

        return null;
    }
}
