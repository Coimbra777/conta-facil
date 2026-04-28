<?php

namespace App\Actions\Charge;

use App\Exceptions\HttpApiException;
use App\Models\Charge;
use App\Support\ChargeStatusTransition;
use Illuminate\Support\Str;

class RejectChargeAction
{
    /**
     * @param  ChargeActionAudience::*  $audience
     */
    public function execute(Charge $charge, ?string $reason, string $audience): Charge
    {
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

        return $charge->fresh()->load(['teamMember', 'expense']);
    }

    /**
     * @param  ChargeActionAudience::*  $audience
     */
    private function assertCanReject(Charge $charge, string $audience): void
    {
        if ($charge->status === 'validated') {
            if ($audience === ChargeActionAudience::PUBLIC_MANAGE) {
                throw new HttpApiException(
                    'Nao e possivel rejeitar um pagamento ja validado.',
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
                    'rejected' => 'Este comprovante ja foi rejeitado.',
                    default => 'So e possivel rejeitar quando houver comprovante aguardando aprovacao.',
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

    private function normalizeReason(?string $reasonRaw): ?string
    {
        if (! is_string($reasonRaw)) {
            return null;
        }

        $reason = trim($reasonRaw);

        return $reason !== '' ? Str::limit($reason, 2000) : null;
    }
}
