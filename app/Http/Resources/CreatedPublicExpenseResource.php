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
            'participant_url' => $this->getPublicUrl(),
            'manage_url' => $this->getManageUrl(),
            /** @deprecated Prefer participant_url + manage_url; mantido por compatibilidade com clientes antigos. */
            'manage_token' => $this->manage_token,
            'manage_path' => '/p/'.$this->public_hash,
        ];
    }
}
