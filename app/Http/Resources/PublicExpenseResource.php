<?php

namespace App\Http\Resources;

use App\Models\Charge;
use App\Support\ManageTokenResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PublicExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $manageToken = ManageTokenResolver::resolve($request);
        $manageOk = $manageToken && hash_equals((string) $this->manage_token, (string) $manageToken);

        if ($manageOk) {
            return $this->toAdminArray($request);
        }

        return $this->toPublicSummaryArray();
    }

    private function toAdminArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_hash' => $this->public_hash,
            'description' => $this->description,
            'total_amount' => $this->total_amount,
            'amount_per_participant' => $this->amount_per_participant,
            'average_amount_per_participant' => $this->amount_per_participant,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'is_closed' => $this->status === 'closed',
            'pix_key' => $this->pix_key,
            'pix_qr_code' => $this->pix_qr_code,
            'owner_name' => $this->owner_name,
            'owner_phone' => $this->owner_phone,
            'can_manage' => true,
            'participants' => $this->whenLoaded('charges', fn () => $this->charges->map(function (Charge $charge) {
                return $charge->publicManageParticipantRow();
            })),
        ];
    }

    /**
     * Visitante sem token de gestão: totais agregados apenas (sem nome/telefone/status por pessoa).
     */
    private function toPublicSummaryArray(): array
    {
        /** @var Collection<int, Charge>|null $charges */
        $charges = $this->relationLoaded('charges') ? $this->charges : null;

        $total = $charges?->count() ?? 0;
        $validated = $charges?->where('status', 'validated')->count() ?? 0;
        $open = $total > 0 ? $total - $validated : 0;

        return [
            'id' => $this->id,
            'public_hash' => $this->public_hash,
            'description' => $this->description,
            'total_amount' => $this->total_amount,
            'amount' => $this->total_amount,
            'amount_per_participant' => $this->amount_per_participant,
            'average_amount_per_participant' => $this->amount_per_participant,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'is_closed' => $this->status === 'closed',
            'pix_key' => $this->pix_key,
            'pix_qr_code' => $this->pix_qr_code,
            'can_manage' => false,
            'participants_total_count' => $total,
            'validated_charges_count' => $validated,
            'open_charges_count' => $open,
        ];
    }
}
