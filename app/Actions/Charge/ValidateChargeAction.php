<?php

namespace App\Actions\Charge;

use App\Exceptions\HttpApiException;
use App\Models\Charge;
use App\Support\ChargeStatusTransition;

class ValidateChargeAction
{
    /**
     * @param  ChargeActionAudience::*  $audience
     */
    public function execute(Charge $charge, string $audience): Charge
    {
        $this->assertCanValidate($charge, $audience);

        ChargeStatusTransition::assertTransition($charge->status, 'validated');

        $charge->update([
            'status' => 'validated',
            'paid_at' => now(),
            'rejection_reason' => null,
        ]);

        $charge->expense?->syncClosedStateFromCharges();

        return $charge->fresh()->load(['teamMember', 'expense']);
    }

    /**
     * @param  ChargeActionAudience::*  $audience
     */
    private function assertCanValidate(Charge $charge, string $audience): void
    {
        if ($charge->status === 'validated') {
            if ($audience === ChargeActionAudience::PUBLIC_MANAGE) {
                throw new HttpApiException(
                    'Este pagamento ja foi validado.',
                    'CHARGE_ALREADY_PAID',
                    422,
                );
            }

            throw new HttpApiException(
                'Charge must have proof_sent status.',
                'INVALID_CHARGE_STATE',
                422,
            );
        }

        if ($charge->status !== 'proof_sent') {
            $message = match ($audience) {
                ChargeActionAudience::PUBLIC_MANAGE => match ($charge->status) {
                    'rejected' => 'Comprovante rejeitado. O participante precisa enviar um novo comprovante pelo link da despesa antes da validacao.',
                    default => 'So e possivel validar apos o participante enviar o comprovante e marcar como pago (status aguardando aprovacao).',
                },
                default => 'Charge must have proof_sent status.',
            };

            throw new HttpApiException(
                $message,
                'INVALID_CHARGE_STATE',
                422,
            );
        }
    }
}
