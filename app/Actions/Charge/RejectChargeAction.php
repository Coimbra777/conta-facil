<?php

namespace App\Actions\Charge;

use App\Exceptions\HttpApiException;
use App\Models\Charge;
use App\Support\ChargeStatusTransition;
use App\Support\ExpenseClosedPolicy;
use Illuminate\Support\Str;

class RejectChargeAction
{
    /**
     * @param  ChargeActionAudience::*  $audience
     */
    public function execute(Charge $charge, string $reason, string $audience): Charge
    {
        $expense = $charge->expense;
        if (! $expense) {
            throw new HttpApiException('Registro não encontrado.', 'NOT_FOUND', 404);
        }

        ExpenseClosedPolicy::assertOpen($expense);

        $this->assertCanReject($charge, $audience);

        ChargeStatusTransition::assertTransition($charge->status, 'rejected');

        $normalizedReason = $this->normalizeReason($reason);

        $charge->update([
            'status' => 'rejected',
            'rejection_reason' => $normalizedReason,
        ]);

        $latestProof = $charge->latestProof();
        if ($latestProof) {
            $latestProof->update(['status' => 'rejected']);
        }

        return $charge->fresh()->load(['expenseParticipant', 'expense']);
    }

    /**
     * @param  ChargeActionAudience::*  $audience
     */
    private function assertCanReject(Charge $charge, string $audience): void
    {
        if ($charge->status === 'validated') {
            throw new HttpApiException(
                'Pagamento já confirmado.',
                'PARTICIPANT_ALREADY_VALIDATED',
                422,
            );
        }

        if ($charge->status !== 'proof_sent') {
            $message = match ($audience) {
                ChargeActionAudience::PUBLIC_MANAGE => match ($charge->status) {
                    'rejected' => 'Este comprovante já foi rejeitado.',
                    default => 'Só é possível rejeitar quando houver comprovante aguardando aprovação.',
                },
                default => 'Só é possível rejeitar quando houver comprovante aguardando aprovação.',
            };

            throw new HttpApiException(
                $message,
                'INVALID_CHARGE_STATE',
                422,
            );
        }
    }

    private function normalizeReason(string $reasonRaw): string
    {
        return Str::limit(trim($reasonRaw), 2000);
    }
}
