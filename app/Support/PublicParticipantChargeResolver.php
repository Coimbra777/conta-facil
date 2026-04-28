<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Expense;

/**
 * Nome (trim) e telefone (só dígitos) devem coincidir exatamente com o cadastro. Não altera dados.
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

        foreach ($expense->charges()->with('teamMember')->get() as $charge) {
            $member = $charge->teamMember;
            if ($member === null) {
                continue;
            }

            $storedDigits = preg_replace('/\D+/', '', (string) $member->phone) ?? '';
            $storedName = trim((string) $member->name);

            if ($storedDigits === $phoneDigits && $storedName === $nameTrim) {
                return $charge;
            }
        }

        return null;
    }
}
