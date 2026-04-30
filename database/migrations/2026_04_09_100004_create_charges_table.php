<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_participant_id')->constrained('expense_participants')->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('expense_id');
            $table->index('expense_participant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
