<?php

namespace Tests\Feature\Expense;

use App\Http\Resources\ChargeResource;
use App\Models\Charge;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseParticipantModelingTest extends TestCase
{
    use RefreshDatabase;

    private function createExpenseCreator(): User
    {
        return User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function expensePayload(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Group dinner',
            'total_amount' => 100.00,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
        ], $overrides);
    }

    public function test_authenticated_participants_create_expense_participants_and_charge_resource_matches(): void
    {
        $admin = $this->createExpenseCreator();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 60.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => $admin->name, 'phone' => '11000000001', 'amount' => 30],
                    ['name' => 'Alice', 'phone' => '11000000002', 'amount' => 30],
                ],
            ])
            ->assertOk();

        $this->assertEquals(
            2,
            ExpenseParticipant::where('expense_id', $expenseId)->count(),
        );
        $this->assertTrue(
            Charge::where('expense_id', $expenseId)->whereNull('expense_participant_id')->doesntExist(),
        );

        $show = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/expenses/{$expenseId}");

        $show->assertOk();
        $charges = $show->json('data.expense.charges');
        $this->assertCount(2, $charges);
        foreach ($charges as $c) {
            $this->assertNotNull($c['participant']);
            $this->assertArrayNotHasKey('member', $c);
        }
    }

    public function test_charge_resource_uses_expense_participant_snapshot(): void
    {
        $admin = User::factory()->create();

        $expense = Expense::factory()->create([
            'created_by' => $admin->id,
            'total_amount' => 50,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
            'amount_per_participant' => 50,
        ]);

        $ep = ExpenseParticipant::create([
            'expense_id' => $expense->id,
            'name' => 'Nome Na Cobrança',
            'phone' => '11000000001',
            'phone_normalized' => '11000000001',
            'amount' => 50,
        ]);

        $charge = Charge::create([
            'expense_id' => $expense->id,
            'expense_participant_id' => $ep->id,
            'description' => $expense->description,
            'amount' => 50,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        $ep->update(['name' => 'Nome Atualizado']);

        $charge->refresh()->load('expenseParticipant');
        $payload = (new ChargeResource($charge))->resolve(request());

        $this->assertSame('Nome Atualizado', $payload['participant']['name']);
        $this->assertArrayNotHasKey('member', $payload);
    }

    public function test_delete_pending_expense_removes_expense_participants_via_cascade(): void
    {
        $admin = $this->createExpenseCreator();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 60.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => $admin->name, 'phone' => '11000000001', 'amount' => 30],
                    ['name' => 'Alice', 'phone' => '11000000002', 'amount' => 30],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseCount('expense_participants', 2);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/expenses/{$expenseId}")
            ->assertOk();

        $this->assertDatabaseMissing('expenses', ['id' => $expenseId]);
        $this->assertDatabaseCount('charges', 0);
        $this->assertDatabaseCount('expense_participants', 0);
    }
}
