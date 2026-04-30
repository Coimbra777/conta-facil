<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExpenseParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id',
        'name',
        'phone',
        'phone_normalized',
        'email',
        'amount',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function charge(): HasOne
    {
        return $this->hasOne(Charge::class, 'expense_participant_id');
    }
}
