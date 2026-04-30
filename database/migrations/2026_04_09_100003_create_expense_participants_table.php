<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('phone_normalized', 32)->nullable();
            $table->string('email')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('expense_id');
            $table->index(['expense_id', 'phone_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_participants');
    }
};
