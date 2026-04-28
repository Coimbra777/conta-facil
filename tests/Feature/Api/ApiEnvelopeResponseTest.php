<?php

namespace Tests\Feature\Api;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEnvelopeResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_expense_get_uses_success_envelope(): void
    {
        $admin = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $admin->id]);
        $member = TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => 'Admin',
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);

        $expense = Expense::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'description' => 'Test',
            'total_amount' => 10.00,
            'amount_per_member' => 10.00,
            'due_date' => now()->addDay()->format('Y-m-d'),
            'pix_key' => 'pix',
            'status' => 'open',
            'public_hash' => 'env-hash-1',
            'manage_token' => 'token-env-1',
        ]);

        Charge::create([
            'expense_id' => $expense->id,
            'team_member_id' => $member->id,
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
