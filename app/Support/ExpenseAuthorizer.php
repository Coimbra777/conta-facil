<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\User;

/**
 * Autorização da cobrança: dono (`created_by`) ou legado admin do time (`team_id`).
 */
final class ExpenseAuthorizer
{
    public static function canManage(?User $user, Expense $expense): bool
    {
        if ($user === null) {
            return false;
        }

        if ($expense->created_by !== null
            && (int) $expense->created_by === (int) $user->id) {
            return true;
        }

        if ($expense->team_id === null) {
            return false;
        }

        $membership = $expense->team->members()->where('user_id', $user->id)->first();

        return $membership !== null && $membership->role === 'admin';
    }
}
