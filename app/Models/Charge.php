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

    protected $fillable = [
        'user_id',
        'team_member_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
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
}
