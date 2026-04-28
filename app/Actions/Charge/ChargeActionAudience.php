<?php

namespace App\Actions\Charge;

/**
 * Origem da operação sobre a cobrança (mensagens diferentes por contexto).
 */
final class ChargeActionAudience
{
    public const TEAM_ADMIN = 'team_admin';

    public const PUBLIC_MANAGE = 'public_manage';
}
