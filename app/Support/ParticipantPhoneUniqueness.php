<?php

namespace App\Support;

use App\Exceptions\HttpApiException;
use Illuminate\Database\QueryException;

final class ParticipantPhoneUniqueness
{
    public const CODE = 'DUPLICATED_PARTICIPANT_PHONE';

    public const MESSAGE = 'Já existe um participante com este telefone nesta despesa.';

    public const INDEX = 'expense_participants_expense_phone_unique';

    public static function makeException(): HttpApiException
    {
        return new HttpApiException(self::MESSAGE, self::CODE, 422);
    }

    public static function matchesQueryException(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return in_array($e->getCode(), ['23000', '23505'], true)
            && (
                str_contains($message, strtolower(self::INDEX))
                || str_contains($message, 'expense_participants.expense_id, expense_participants.phone_normalized')
            );
    }
}
