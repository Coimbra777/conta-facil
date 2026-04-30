<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'owner_name',
        'owner_phone',
        'description',
        'total_amount',
        'amount_per_participant',
        'due_date',
        'pix_key',
        'pix_qr_code',
        'status',
    ];

    protected $hidden = [
        'manage_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            if (empty($expense->public_hash)) {
                $expense->public_hash = (string) Str::uuid();
            }
            if (empty($expense->manage_token)) {
                $expense->manage_token = (string) Str::uuid();
            }
        });

        static::saving(function (Expense $expense) {
            if (! in_array($expense->status, ['open', 'closed'], true)) {
                throw new \DomainException('Status de despesa invalido: apenas open ou closed.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'amount_per_participant' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function getPublicUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/p/'.$this->public_hash;
    }

    /**
     * Link para o organizador (fragmento com token — adequado ao SPA em /p/:hash).
     */
    public function getManageUrl(): string
    {
        return $this->getPublicUrl().'#manage='.rawurlencode((string) $this->manage_token);
    }

    public function scopeByHash($query, string $hash)
    {
        return $query->where('public_hash', $hash);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function expenseParticipants(): HasMany
    {
        return $this->hasMany(ExpenseParticipant::class);
    }

    public function participants(): HasMany
    {
        return $this->expenseParticipants();
    }

    /**
     * Se todas as cobranças estiverem validadas, marca a despesa como encerrada (MVP).
     */
    public function syncClosedStateFromCharges(): void
    {
        if (! $this->charges()->exists()) {
            return;
        }

        $allValidated = $this->charges()->where('status', '!=', 'validated')->doesntExist();

        if ($allValidated && $this->status !== 'closed') {
            $this->update(['status' => 'closed']);
        }
    }
}
