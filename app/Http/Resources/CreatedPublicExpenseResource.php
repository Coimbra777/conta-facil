<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Expense */
class CreatedPublicExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_hash' => $this->public_hash,
            'manage_token' => $this->manage_token,
            'public_url' => $this->getPublicUrl(),
            'manage_url' => $this->getPublicUrl(),
            'manage_path' => '/p/'.$this->public_hash,
        ];
    }
}
