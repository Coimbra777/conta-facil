<?php

namespace App\Http\Resources;

use App\Models\Charge;
use App\Support\ManageTokenResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $manageToken = ManageTokenResolver::resolve($request);
        $manageOk = $manageToken && hash_equals((string) $this->manage_token, (string) $manageToken);

        if ($manageOk) {
            return $this->toAdminArray($request);
        }

        return $this->toPublicArray();
    }

    private function toAdminArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_hash' => $this->public_hash,
            'description' => $this->description,
            'total_amount' => $this->total_amount,
            'amount_per_member' => $this->amount_per_member,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'is_closed' => $this->status === 'closed',
            'pix_key' => $this->pix_key,
            'pix_qr_code' => $this->pix_qr_code,
            'owner_name' => $this->owner_name,
            'owner_phone' => $this->owner_phone,
            'can_manage' => true,
            'members' => $this->whenLoaded('charges', fn () => $this->charges->map(function (Charge $charge) {
                return self::memberRowFromCharge($charge);
            })),
        ];
    }

    private function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'public_hash' => $this->public_hash,
            'description' => $this->description,
            'total_amount' => $this->total_amount,
            'amount' => $this->total_amount,
            'amount_per_member' => $this->amount_per_member,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'is_closed' => $this->status === 'closed',
            'pix_key' => $this->pix_key,
            'pix_qr_code' => $this->pix_qr_code,
            'can_manage' => false,
            'participants' => $this->whenLoaded('charges', fn () => $this->charges->map(function (Charge $charge) {
                $row = self::participantSnapshotFromCharge($charge);

                return [
                    'name' => $row['name'],
                    'status' => $charge->status,
                ];
            })),
        ];
    }

    /**
     * @return array{id: int|string|null, name: string|null, phone: string|null, charge_id: int|string, charge_status: mixed, amount: mixed}
     */
    private static function memberRowFromCharge(Charge $charge): array
    {
        $charge->loadMissing(['expenseParticipant', 'teamMember']);
        $row = self::participantSnapshotFromCharge($charge);

        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'charge_id' => $charge->id,
            'charge_status' => $charge->status,
            'amount' => $charge->amount,
        ];
    }

    /**
     * @return array{id: int|string|null, name: string|null, phone: string|null}
     */
    private static function participantSnapshotFromCharge(Charge $charge): array
    {
        $charge->loadMissing(['expenseParticipant', 'teamMember']);
        if ($charge->expenseParticipant) {
            $p = $charge->expenseParticipant;

            return [
                'id' => $p->id,
                'name' => $p->name,
                'phone' => $p->phone,
            ];
        }

        $m = $charge->teamMember;

        return [
            'id' => $m?->id,
            'name' => $m?->name,
            'phone' => $m?->phone,
        ];
    }
}
