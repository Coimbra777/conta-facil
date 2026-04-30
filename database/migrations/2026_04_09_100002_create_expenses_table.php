<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owner_name')->nullable();
            $table->string('owner_phone', 32)->nullable();
            $table->string('description');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('amount_per_participant', 10, 2)->nullable();
            $table->date('due_date');
            $table->string('pix_key')->nullable();
            $table->text('pix_qr_code')->nullable();
            $table->string('status')->default('open');
            $table->string('public_hash')->nullable()->unique();
            $table->string('manage_token', 64)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
