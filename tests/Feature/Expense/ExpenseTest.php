<?php

namespace Tests\Feature\Expense;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function createTeamWithMembers(int $memberCount): array
    {
        $admin = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => $admin->name,
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);

        for ($i = 1; $i < $memberCount; $i++) {
            TeamMember::create([
                'team_id' => $team->id,
                'name' => "Member {$i}",
                'phone' => '1100000000'.($i + 1),
                'role' => 'member',
            ]);
        }

        return [$team, $admin];
    }

    private function expensePayload(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Team dinner',
            'total_amount' => 100.00,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
        ], $overrides);
    }

    public function test_expense_splits_correctly_among_members(): void
    {
        [$team, $admin] = $this->createTeamWithMembers(3);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", $this->expensePayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'meta',
                'data' => [
                    'expense' => ['id', 'description', 'total_amount', 'status', 'public_hash', 'pix_key', 'charges'],
                ],
            ]);

        $this->assertDatabaseHas('expenses', [
            'team_id' => $team->id,
            'total_amount' => '100.00',
            'status' => 'open',
            'pix_key' => '11999999999',
        ]);
    }

    public function test_correct_number_of_charges_created(): void
    {
        [$team, $admin] = $this->createTeamWithMembers(4);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", $this->expensePayload([
                'total_amount' => 200.00,
            ]));

        $this->assertDatabaseCount('charges', 4);
    }

    public function test_rounding_is_handled_correctly(): void
    {
        [$team, $admin] = $this->createTeamWithMembers(3);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", $this->expensePayload([
                'total_amount' => 10.00,
            ]));

        $charges = \App\Models\Charge::orderBy('id')->get();
        $this->assertCount(3, $charges);

        $total = $charges->sum(fn ($c) => (float) $c->amount);
        $this->assertEquals(10.00, $total);

        $this->assertEquals('3.34', $charges[0]->amount);
        $this->assertEquals('3.33', $charges[1]->amount);
        $this->assertEquals('3.33', $charges[2]->amount);
    }

    public function test_expense_has_public_hash(): void
    {
        [$team, $admin] = $this->createTeamWithMembers(2);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", $this->expensePayload());

        $hash = $response->json('data.expense.public_hash');
        $this->assertNotNull($hash);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $hash);
    }

    public function test_expense_has_pix_key(): void
    {
        [$team, $admin] = $this->createTeamWithMembers(2);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", $this->expensePayload([
                'pix_key' => 'meu@email.com',
            ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', ['pix_key' => 'meu@email.com']);
    }

    public function test_amount_per_member_is_calculated(): void
    {
        [$team, $admin] = $this->createTeamWithMembers(4);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", $this->expensePayload([
                'total_amount' => 100.00,
            ]));

        $this->assertEquals('25.00', $response->json('data.expense.amount_per_member'));
    }

    public function test_charges_created_with_pending_status(): void
    {
        [$team, $admin] = $this->createTeamWithMembers(2);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", $this->expensePayload());

        $this->assertDatabaseCount('charges', 2);
        $charges = \App\Models\Charge::all();
        foreach ($charges as $charge) {
            $this->assertEquals('pending', $charge->status);
        }
    }

    public function test_non_admin_cannot_create_expense(): void
    {
        $admin = User::factory()->create();
        $regularUser = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => $admin->name,
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $regularUser->id,
            'name' => $regularUser->name,
            'phone' => '11000000002',
            'email' => $regularUser->email,
            'role' => 'member',
        ]);

        $response = $this->actingAs($regularUser, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", $this->expensePayload());

        $response->assertStatus(403);
    }

    public function test_show_expense_includes_charges_with_member(): void
    {
        [$team, $admin] = $this->createTeamWithMembers(2);

        $createResponse = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", $this->expensePayload([
                'total_amount' => 50.00,
            ]));

        $expenseId = $createResponse->json('data.expense.id');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/teams/{$team->id}/expenses/{$expenseId}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'meta',
                'data' => [
                    'expense' => [
                        'id', 'description', 'total_amount', 'status',
                        'charges' => [
                            '*' => ['id', 'amount', 'status', 'member'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_pix_key_is_required(): void
    {
        [$team, $admin] = $this->createTeamWithMembers(2);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", [
                'description' => 'Test',
                'total_amount' => 50.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('pix_key');
    }

    public function test_admin_can_patch_expense(): void
    {
        [$team, $admin] = $this->createTeamWithMembers(2);

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", $this->expensePayload());

        $expenseId = $create->json('data.expense.id');
        $newDue = now()->addDays(10)->format('Y-m-d');

        $patch = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/teams/{$team->id}/expenses/{$expenseId}", [
                'description' => 'Atualizado',
                'total_amount' => 120.00,
                'due_date' => $newDue,
                'pix_key' => 'meu@pix.com',
            ]);

        $patch->assertOk()
            ->assertJsonPath('data.expense.description', 'Atualizado')
            ->assertJsonPath('data.expense.total_amount', '120.00');

        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'description' => 'Atualizado',
            'pix_key' => 'meu@pix.com',
        ]);
    }

    public function test_admin_can_add_participants_when_all_charges_pending(): void
    {
        [$team, $admin] = $this->createTeamWithMembers(2);

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", $this->expensePayload([
                'total_amount' => 90.00,
            ]));

        $expenseId = $create->json('data.expense.id');

        $add = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => 'Novo Membro', 'phone' => '11888887777'],
                ],
            ]);

        $add->assertOk();
        $this->assertDatabaseCount('charges', 3);

        $charges = \App\Models\Charge::where('expense_id', $expenseId)->orderBy('id')->get();
        $total = $charges->sum(fn ($c) => (float) $c->amount);
        $this->assertEquals(90.00, $total);
    }

    public function test_non_admin_cannot_patch_expense(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $admin->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => $admin->name,
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'name' => $member->name,
            'phone' => '11000000002',
            'email' => $member->email,
            'role' => 'member',
        ]);

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/teams/{$team->id}/expenses", $this->expensePayload());
        $expenseId = $create->json('data.expense.id');

        $patch = $this->actingAs($member, 'sanctum')
            ->patchJson("/api/v1/teams/{$team->id}/expenses/{$expenseId}", [
                'description' => 'Hack',
                'total_amount' => 50.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
                'pix_key' => '11999999999',
            ]);

        $patch->assertStatus(403);
    }
}
