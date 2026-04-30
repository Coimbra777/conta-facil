<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Charge extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'proof_sent', 'validated', 'rejected'];

    /** @var list<string> */
    public const EAGER_WITH_PARTICIPANT = ['expenseParticipant'];

    /** @var list<string> */
    public const EAGER_WITH_PARTICIPANT_AND_PROOFS = ['expenseParticipant', 'paymentProofs'];

    protected $fillable = [
        'expense_participant_id',
        'expense_id',
        'description',
        'amount',
        'due_date',
        'status',
        'rejection_reason',
        'paid_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (Charge $charge) {
            if (! in_array($charge->status, self::STATUSES, true)) {
                throw new \DomainException('Status de cobranca invalido: '.$charge->status);
            }

            if ($charge->status !== 'rejected') {
                $charge->rejection_reason = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function expenseParticipant(): BelongsTo
    {
        return $this->belongsTo(ExpenseParticipant::class);
    }

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function latestProof(): ?PaymentProof
    {
        return $this->paymentProofs()->latest()->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function participantPayload(): ?array
    {
        $this->loadMissing('expenseParticipant');
        $p = $this->expenseParticipant;
        if ($p === null) {
            return null;
        }

        return [
            'id' => $p->id,
            'name' => $p->name,
            'phone' => $p->phone,
            'phone_normalized' => $p->phone_normalized,
            'email' => $p->email,
            'amount' => $p->amount,
        ];
    }

    /**
     * @return array{name: string, phone: string}
     */
    public function participantIdentity(): array
    {
        $this->loadMissing('expenseParticipant');
        $p = $this->expenseParticipant;

        return [
            'name' => trim((string) ($p?->name ?? '')),
            'phone' => (string) ($p?->phone ?? $p?->phone_normalized ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicManageParticipantRow(): array
    {
        $row = $this->participantIdentity();

        return [
            'charge_id' => $this->id,
            'charge_status' => $this->status,
            'amount' => $this->amount,
            'name' => $row['name'],
            'phone' => $row['phone'],
        ];
    }

    /**
     * @return array<int|string, callable|string>
     */
    public static function eagerChargesWithParticipant(): array
    {
        return [
            'charges' => fn ($q) => $q->with(self::EAGER_WITH_PARTICIPANT),
        ];
    }

    /**
     * @return array<int|string, callable|string>
     */
    public static function eagerChargesWithParticipantAndProofs(): array
    {
        return [
            'charges' => fn ($q) => $q->with(self::EAGER_WITH_PARTICIPANT_AND_PROOFS),
        ];
    }
}
