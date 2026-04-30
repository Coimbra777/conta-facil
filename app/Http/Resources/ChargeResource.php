<?php

namespace App\Http\Resources;

use App\Models\Charge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Charge $charge */
        $charge = $this->resource;
        $charge->loadMissing(['expenseParticipant', 'teamMember']);

        $participantPayload = $this->resolveParticipantPayload($charge);

        return [
            'id' => $this->id,
            'description' => $this->description,
            'amount' => $this->amount,
            'status' => $this->status,
            'due_date' => $this->due_date,
            'paid_at' => $this->paid_at,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'participant' => $participantPayload,
            'member' => $participantPayload,
            'proof_status' => $this->whenLoaded('paymentProofs', function () {
                return $this->paymentProofs->sortByDesc('id')->first()?->status;
            }),
            'has_proof' => $this->whenLoaded('paymentProofs', fn () => $this->paymentProofs->isNotEmpty()),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveParticipantPayload(Charge $charge): ?array
    {
        if ($charge->relationLoaded('expenseParticipant') && $charge->expenseParticipant) {
            return (new ExpenseParticipantResource($charge->expenseParticipant))->resolve(request());
        }

        if ($charge->relationLoaded('teamMember') && $charge->teamMember) {
            return (new TeamMemberResource($charge->teamMember))->resolve(request());
        }

        return null;
    }
}
