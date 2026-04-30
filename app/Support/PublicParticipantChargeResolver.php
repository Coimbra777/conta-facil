<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Expense;

/**
 * Nome (trim) e telefone (só dígitos) devem coincidir exatamente com o snapshot da cobrança
 * ({@see \App\Models\ExpenseParticipant}), com fallback legado para {@see \App\Models\TeamMember}.
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

        foreach ($expense->charges()->with(['expenseParticipant', 'teamMember'])->get() as $charge) {
            $storedName = null;
            $storedDigits = '';

            if ($charge->expenseParticipant) {
                $storedName = trim((string) $charge->expenseParticipant->name);
                $storedDigits = PhoneNormalizer::digits((string) $charge->expenseParticipant->phone);
            } elseif ($charge->teamMember) {
                $storedName = trim((string) $charge->teamMember->name);
                $storedDigits = PhoneNormalizer::digits((string) $charge->teamMember->phone);
            }

            if ($storedName === null) {
                continue;
            }

            if ($storedDigits === $phoneDigits && $storedName === $nameTrim) {
                return $charge;
            }
        }

        return null;
    }
}
