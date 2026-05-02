<?php

namespace App\Actions\Charge;

use App\Exceptions\HttpApiException;
use App\Models\Charge;
use App\Services\ExpenseService;
use App\Support\ChargeStatusTransition;
use App\Support\ExpenseClosedPolicy;

class ValidateChargeAction
{
    public function __construct(
        private ExpenseService $expenseService,
    ) {}

    /**
     * @param  ChargeActionAudience::*  $audience
     */
    public function execute(Charge $charge, string $audience): Charge
    {
        $expense = $charge->expense;
        if (! $expense) {
            throw new HttpApiException('Registro não encontrado.', 'NOT_FOUND', 404);
        }

        ExpenseClosedPolicy::assertOpen($expense);

        $this->assertCanValidate($charge, $audience);

        ChargeStatusTransition::assertTransition($charge->status, 'validated');

        $charge->update([
            'status' => 'validated',
            'paid_at' => now(),
            'rejection_reason' => null,
        ]);

        if ($expense) {
            $this->expenseService->closeExpenseWhenAllChargesValidated($expense);
        }

        return $charge->fresh()->load(['expenseParticipant', 'expense']);
    }

    /**
     * @param  ChargeActionAudience::*  $audience
     */
    private function assertCanValidate(Charge $charge, string $audience): void
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
                    'rejected' => 'Comprovante rejeitado. O participante precisa enviar um novo comprovante pelo link da despesa antes da validação.',
                    default => 'Só é possível validar após o participante enviar um comprovante para aprovação.',
                },
                default => 'Só é possível validar quando houver comprovante aguardando aprovação.',
            };

            throw new HttpApiException(
                $message,
                'INVALID_CHARGE_STATE',
                422,
            );
        }
    }
}
