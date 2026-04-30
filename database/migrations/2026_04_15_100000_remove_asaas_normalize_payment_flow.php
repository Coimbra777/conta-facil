<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('charges')) {
            $replacements = [
                'RECEIVED' => 'validated',
                'CONFIRMED' => 'validated',
                'RECEIVED_IN_CASH' => 'validated',
                'PENDING' => 'pending',
                'OVERDUE' => 'pending',
            ];

            foreach ($replacements as $from => $to) {
                DB::table('charges')->where('status', $from)->update(['status' => $to]);
            }
        }

        if (Schema::hasTable('expenses')) {
            $expenseMap = [
                'PAID' => 'paid',
                'PARTIALLY_PAID' => 'partially_paid',
                'OVERDUE' => 'overdue',
            ];

            foreach ($expenseMap as $from => $to) {
                DB::table('expenses')->where('status', $from)->update(['status' => $to]);
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'asaas_customer_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['asaas_customer_id']);
            });
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('asaas_customer_id');
            });
        }

        if (Schema::hasTable('charges')) {
            if (Schema::hasColumn('charges', 'asaas_charge_id')) {
                Schema::table('charges', function (Blueprint $table) {
                    $table->dropUnique(['asaas_charge_id']);
                });
            }

            $toDrop = [];
            foreach (['asaas_charge_id', 'pix_qr_code', 'pix_copy_paste', 'payment_link'] as $col) {
                if (Schema::hasColumn('charges', $col)) {
                    $toDrop[] = $col;
                }
            }
            if ($toDrop !== []) {
                Schema::table('charges', function (Blueprint $table) use ($toDrop) {
                    $table->dropColumn($toDrop);
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('asaas_customer_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('charges')) {
            Schema::table('charges', function (Blueprint $table) {
                $table->string('asaas_charge_id')->nullable()->after('due_date');
                $table->longText('pix_qr_code')->nullable()->after('status');
                $table->text('pix_copy_paste')->nullable()->after('pix_qr_code');
                $table->string('payment_link')->nullable()->after('pix_copy_paste');
            });
        }
    }
};
