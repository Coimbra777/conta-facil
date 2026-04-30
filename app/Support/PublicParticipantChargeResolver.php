<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Expense;

/**
 * Resolve cobrança por nome + telefone no fluxo público.
 */
class PublicParticipantChargeResolver
{
    public static function findChargeForExactPublicParticipant(
        Expense $expense,
        string $nameInput,
        string $phoneRaw,
    ): ?Charge {
        $nameTrim = trim($nameInput);
        $phoneDigits = preg_replace('/\D+/', '', $phoneRaw) ?? '';

        if ($nameTrim === '' || $phoneDigits === '' || strlen($phoneDigits) < 10) {
            return null;
        }

        foreach ($expense->charges()->with(Charge::EAGER_WITH_PARTICIPANT)->get() as $charge) {
            $row = $charge->participantIdentity();
            $storedName = trim((string) ($row['name'] ?? ''));
            if ($storedName === '') {
                continue;
            }

            $storedDigits = PhoneNormalizer::digits((string) ($row['phone'] ?? ''));

            if ($storedDigits === $phoneDigits && $storedName === $nameTrim) {
                return $charge;
            }
        }

        return null;
    }
}
