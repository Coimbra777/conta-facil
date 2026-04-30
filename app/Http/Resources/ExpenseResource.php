<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\ExpenseAuthorizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            'id' => $this->id,
            'description' => $this->description,
            'total_amount' => $this->total_amount,
            'due_date' => $this->due_date,
            'amount_per_participant' => $this->amount_per_participant,
            'average_amount_per_participant' => $this->amount_per_participant,
            'pix_key' => $this->pix_key,
            'pix_qr_code' => $this->pix_qr_code,
            'status' => $this->status,
            'public_hash' => $this->public_hash,
            'public_url' => $this->public_hash ? $this->getPublicUrl() : null,
            'charges' => ChargeResource::collection($this->whenLoaded('charges')),
            'created_at' => $this->created_at,
            'can_manage' => $this->resolveCanManage($user),
        ];
    }

    private function resolveCanManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        /** @var \App\Models\Expense $expense */
        $expense = $this->resource;

        return ExpenseAuthorizer::canManage($user, $expense);
    }
}
