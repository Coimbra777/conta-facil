<?php

namespace App\Actions\Charge;

use App\Exceptions\Domain\ChargeAlreadyPaidException;
use App\Exceptions\Domain\ChargeProofAlreadySentException;
use App\Exceptions\Domain\InvalidChargeStateException;
use App\Exceptions\HttpApiException;
use App\Models\Charge;
use App\Models\Expense;
use App\Services\PaymentProofService;
use App\Support\ChargeStatusTransition;
use App\Support\PublicParticipantChargeResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SubmitPaymentProofAction
{
    public function __construct(private PaymentProofService $paymentProofService) {}

    public function execute(Expense $expense, string $name, string $phone, UploadedFile $file): Charge
    {
        $charge = PublicParticipantChargeResolver::findChargeForExactPublicParticipant(
            $expense,
            $name,
            $phone,
        );

        if ($charge === null) {
            throw new HttpApiException(
                'Participante não encontrado nesta despesa.',
                'PARTICIPANT_NOT_FOUND',
                422,
            );
        }

        $charge->refresh();

        $this->assertEligibleForSubmission($charge);

        return DB::transaction(function () use ($charge, $file) {
            try {
                $this->paymentProofService->uploadProof($charge, $file);
            } catch (HttpApiException $e) {
                $fresh = $charge->fresh();
                throw new HttpApiException(
                    $e->getMessage(),
                    $e->errorCode,
                    $e->status,
                    [
                        'status' => $fresh?->status ?? $charge->status,
                        'rejection_reason' => $fresh?->rejection_reason,
                    ],
                );
            }

            $charge->refresh();

            ChargeStatusTransition::assertTransition($charge->status, 'proof_sent');
            $charge->update(['status' => 'proof_sent']);

            return $charge->fresh()->load(Charge::EAGER_WITH_PARTICIPANT);
        });
    }

    private function assertEligibleForSubmission(Charge $charge): void
    {
        if ($charge->status === 'validated') {
            throw ChargeAlreadyPaidException::make(
                'Pagamento já confirmado.',
                ['status' => 'validated', 'rejection_reason' => null],
            );
        }

        if ($charge->status === 'proof_sent') {
            throw ChargeProofAlreadySentException::make(
                'Comprovante já enviado.',
                ['status' => 'proof_sent', 'rejection_reason' => null],
            );
        }

        if (! in_array($charge->status, ['pending', 'rejected'], true)) {
            throw InvalidChargeStateException::make(
                'Não é possível enviar comprovante neste estado.',
                [
                    'status' => $charge->status,
                    'rejection_reason' => $charge->rejection_reason,
                ],
            );
        }
    }
}
