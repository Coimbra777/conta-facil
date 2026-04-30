<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->foreignId('expense_participant_id')
                ->nullable()
                ->after('team_member_id')
                ->constrained('expense_participants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_participant_id');
        });
    }
};
