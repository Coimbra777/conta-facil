<?php

namespace Tests\Feature\Api;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEnvelopeResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_expense_get_uses_success_envelope(): void
    {
        $admin = User::factory()->create();

        $expense = Expense::create([
            'created_by' => $admin->id,
            'description' => 'Test',
            'total_amount' => 10.00,
            'amount_per_participant' => 10.00,
            'due_date' => now()->addDay()->format('Y-m-d'),
            'pix_key' => 'pix',
            'status' => 'open',
        ]);
        $expense->forceFill([
            'public_hash' => 'env-hash-1',
            'manage_token' => 'token-env-1',
        ])->save();

        $ep = ExpenseParticipant::create([
            'expense_id' => $expense->id,
            'name' => 'Admin',
            'phone' => '11000000001',
            'phone_normalized' => '11000000001',
            'amount' => 10.00,
        ]);

        Charge::create([
            'expense_id' => $expense->id,
            'expense_participant_id' => $ep->id,
            'amount' => 10.00,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        $this->getJson('/api/v1/public/expenses/env-hash-1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['expense'], 'meta'])
            ->assertJsonPath('data.expense.description', 'Test');
    }
}
