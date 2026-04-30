<?php

use App\Models\Charge;
use App\Models\ExpenseParticipant;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Charge::query()
            ->whereNull('expense_participant_id')
            ->whereNotNull('team_member_id')
            ->whereNotNull('expense_id')
            ->with('teamMember')
            ->orderBy('id')
            ->chunkById(100, function ($charges): void {
                foreach ($charges as $charge) {
                    $tm = $charge->teamMember;
                    if (! $tm) {
                        continue;
                    }

                    $digits = PhoneNormalizer::digits((string) $tm->phone);

                    $ep = ExpenseParticipant::create([
                        'expense_id' => $charge->expense_id,
                        'name' => $tm->name,
                        'phone' => $tm->phone,
                        'phone_normalized' => $digits !== '' ? $digits : null,
                        'email' => $tm->email,
                        'amount' => $charge->amount,
                        'metadata' => null,
                    ]);

                    $charge->expense_participant_id = $ep->id;
                    $charge->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        Charge::query()->whereNotNull('expense_participant_id')->update([
            'expense_participant_id' => null,
        ]);
        ExpenseParticipant::query()->delete();
    }
};
