<?php

namespace App\Services;

use App\Helpers\ApiWhatsappHelper;
use App\Models\Charge;
use App\Models\Expense;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(private ApiWhatsappHelper $whatsappHelper) {}

    public function sendChargeNotification(TeamMember $member, Charge $charge, ?Expense $expense = null): void
    {
        $this->deliverChargeWhatsApp(
            $member->name,
            $member->phone,
            $charge,
            $expense,
            ['team_member_id' => $member->id],
        );
    }

    /**
     * Notifica destinatário usando snapshot em {@see Charge::expenseParticipant} ou legado {@see Charge::teamMember}.
     */
    public function notifyChargeRecipient(Charge $charge, ?Expense $expense = null): void
    {
        $charge->loadMissing(['expenseParticipant', 'teamMember']);

        $participant = $charge->expenseParticipant;
        $member = $charge->teamMember;

        $name = $participant?->name ?? $member?->name;
        $phone = $participant?->phone ?? $member?->phone;
        $log = $participant !== null
            ? ['expense_participant_id' => $participant->id]
            : ['team_member_id' => $member?->id];

        $this->deliverChargeWhatsApp($name, $phone, $charge, $expense, $log);
    }

    /**
     * @param  array<string, int|null>  $logContext
     */
    private function deliverChargeWhatsApp(
        ?string $name,
        ?string $phone,
        Charge $charge,
        ?Expense $expense,
        array $logContext,
    ): void {
        $displayName = $name !== null && trim($name) !== '' ? trim($name) : 'Participante';

        $message = "Ola {$displayName}! Voce tem uma cobranca de R$ {$charge->amount} "
            ."com vencimento em {$charge->due_date->format('d/m/Y')}. "
            ."Descricao: {$charge->description}";

        if ($expense?->public_hash) {
            $message .= "\nAcesse o link para pagar: {$expense->getPublicUrl()}";
        }

        if ($phone === null || trim((string) $phone) === '') {
            Log::info('No notification channel available for member (missing phone)', [
                ...$logContext,
                'charge_id' => $charge->id,
            ]);

            return;
        }

        $sent = $this->whatsappHelper->send((string) $phone, $message);

        if (! $sent) {
            Log::info('WhatsApp notification not delivered (API disabled or failed)', [
                ...$logContext,
                'charge_id' => $charge->id,
            ]);
        }
    }
}
