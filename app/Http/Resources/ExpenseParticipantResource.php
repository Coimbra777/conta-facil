<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ExpenseParticipant */
class ExpenseParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'role' => 'participant',
            'has_account' => false,
            'user_id' => null,
            'amount' => $this->amount,
            'created_at' => $this->created_at,
        ];
    }
}
