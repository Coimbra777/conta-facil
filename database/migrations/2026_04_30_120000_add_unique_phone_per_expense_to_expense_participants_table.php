<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('expense_participants')
            ->select('expense_id', 'phone_normalized', DB::raw('COUNT(*) as total'))
            ->whereNotNull('phone_normalized')
            ->groupBy('expense_id', 'phone_normalized')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Não foi possível aplicar a unique expense_participants_expense_phone_unique. '.
                'Existem telefones duplicados na mesma despesa em expense_participants. '.
                'Corrija os dados antes de rodar esta migration novamente.'
            );
        }

        Schema::table('expense_participants', function (Blueprint $table) {
            $table->unique(
                ['expense_id', 'phone_normalized'],
                'expense_participants_expense_phone_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('expense_participants', function (Blueprint $table) {
            $table->dropUnique('expense_participants_expense_phone_unique');
        });
    }
};
