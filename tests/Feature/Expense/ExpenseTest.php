<?php

namespace Tests\Feature\Expense;

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\PaymentProof;
use App\Models\User;
use App\Support\ExpenseClosedPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        return User::factory()->create();
    }

    private function expensePayload(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Shared expense',
            'total_amount' => 100.00,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
        ], $overrides);
    }

    /**
     * @return list<array{name: string, phone: string, amount: float}>
     */
    private function participantRowsForCount(User $admin, int $participantCount, float $total): array
    {
        $this->assertGreaterThan(0, $participantCount);
        $names = [$admin->name];
        $phones = ['11900000001'];
        for ($i = 1; $i < $participantCount; $i++) {
            $names[] = "Participant {$i}";
            $phones[] = '1190000000'.($i + 1);
        }

        $totalCents = (int) round($total * 100);
        $base = intdiv($totalCents, $participantCount);
        $remainder = $totalCents % $participantCount;
        $rows = [];
        for ($i = 0; $i < $participantCount; $i++) {
            $cents = $base + ($i < $remainder ? 1 : 0);
            $rows[] = [
                'name' => $names[$i],
                'phone' => $phones[$i],
                'amount' => round($cents / 100, 2),
            ];
        }

        return $rows;
    }

    private function postParticipantsSplit(User $admin, int|string $expenseId, int $participantCount, float $total): void
    {
        $participants = $this->participantRowsForCount($admin, $participantCount, $total);
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => $participants,
            ])
            ->assertOk();
    }

    public function test_expense_splits_correctly_among_participants(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());

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
            'created_by' => $admin->id,
            'total_amount' => '100.00',
            'status' => 'open',
            'pix_key' => '11999999999',
        ]);
    }

    public function test_correct_number_of_charges_created(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 200.00,
            ]));
        $response->assertStatus(201);
        $this->assertDatabaseCount('charges', 0);

        $expenseId = $response->json('data.expense.id');
        $this->postParticipantsSplit($admin, $expenseId, 4, 200.00);

        $this->assertDatabaseCount('charges', 4);
    }

    public function test_store_expense_rejects_past_due_date_in_portuguese(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'due_date' => now()->subDay()->format('Y-m-d'),
            ]))
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Informe uma data de vencimento igual ou posterior a hoje.',
            )
            ->assertJsonPath(
                'errors.due_date.0',
                'Informe uma data de vencimento igual ou posterior a hoje.',
            )
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_store_expense_rejects_missing_due_date_in_portuguese(): void
    {
        $admin = $this->createAdminUser();
        $payload = $this->expensePayload();
        unset($payload['due_date']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Informe a data de vencimento.')
            ->assertJsonPath('errors.due_date.0', 'Informe a data de vencimento.')
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_store_expense_rejects_missing_pix_key_in_portuguese(): void
    {
        $admin = $this->createAdminUser();
        $payload = $this->expensePayload();
        unset($payload['pix_key']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Informe a chave Pix.')
            ->assertJsonPath('errors.pix_key.0', 'Informe a chave Pix.')
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_add_participants_rejects_invalid_brazilian_phone(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());
        $create->assertStatus(201);

        $expenseId = $create->json('data.expense.id');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => 'Inválido', 'phone' => '1193334444', 'amount' => 100],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('participants.0.phone');

        $this->assertSame(
            'Telefone inválido. Use um número com DDD.',
            ($response->json('errors')['participants.0.phone'] ?? [null])[0],
        );
    }

    public function test_add_participants_accepts_sum_equal_total(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 100.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => 'A', 'phone' => '11900000001', 'amount' => 50],
                    ['name' => 'B', 'phone' => '11900000002', 'amount' => 50],
                ],
            ]);

        $response->assertOk();

        $charges = \App\Models\Charge::where('expense_id', $expenseId)->get();
        $this->assertCount(2, $charges);
        $this->assertEquals(100.0, round($charges->sum(fn ($c) => (float) $c->amount), 2));
    }

    public function test_add_participants_accepts_sum_above_total(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 100.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => 'A', 'phone' => '11900000001', 'amount' => 70],
                    ['name' => 'B', 'phone' => '11900000002', 'amount' => 50],
                ],
            ]);

        $response->assertOk();

        $charges = \App\Models\Charge::where('expense_id', $expenseId)->orderBy('id')->get();
        $this->assertCount(2, $charges);
        $this->assertEquals(120.0, round($charges->sum(fn ($c) => (float) $c->amount), 2));
        $this->assertEquals(70.0, (float) $charges[0]->amount);
        $this->assertEquals(50.0, (float) $charges[1]->amount);
    }

    public function test_add_participants_rejects_missing_name(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());
        $expenseId = $create->json('data.expense.id');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => '', 'phone' => '11999998888', 'amount' => 100],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('participants.0.name');

        $this->assertSame(
            'Informe o nome do participante.',
            ($response->json('errors')['participants.0.name'] ?? [null])[0],
        );
    }

    public function test_add_participants_rejects_missing_phone(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());
        $expenseId = $create->json('data.expense.id');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => 'Sem telefone', 'phone' => '', 'amount' => 100],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('participants.0.phone');

        $this->assertSame(
            'Informe o telefone do participante.',
            ($response->json('errors')['participants.0.phone'] ?? [null])[0],
        );
    }

    public function test_rounding_is_handled_correctly(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 10.00,
            ]));
        $create->assertStatus(201);
        $expenseId = $create->json('data.expense.id');
        $this->postParticipantsSplit($admin, $expenseId, 3, 10.00);

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
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());

        $hash = $response->json('data.expense.public_hash');
        $this->assertNotNull($hash);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $hash);
    }

    public function test_expense_has_pix_key(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'pix_key' => 'meu@email.com',
            ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', ['pix_key' => 'meu@email.com']);
    }

    public function test_amount_per_participant_is_calculated(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 100.00,
            ]));
        $create->assertStatus(201);
        $this->assertEquals('0.00', $create->json('data.expense.amount_per_participant'));

        $expenseId = $create->json('data.expense.id');
        $sync = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => $this->participantRowsForCount($admin, 4, 100.00),
            ]);
        $sync->assertOk();
        $this->assertEquals('25.00', $sync->json('data.expense.amount_per_participant'));
    }

    public function test_charges_created_with_pending_status(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());
        $create->assertStatus(201);
        $expenseId = $create->json('data.expense.id');
        $this->postParticipantsSplit($admin, $expenseId, 2, 100.00);

        $this->assertDatabaseCount('charges', 2);
        $charges = \App\Models\Charge::all();
        foreach ($charges as $charge) {
            $this->assertEquals('pending', $charge->status);
        }
    }

    public function test_authenticated_user_can_create_own_expense(): void
    {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());

        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', [
            'created_by' => $regularUser->id,
        ]);
    }

    public function test_show_expense_includes_charges_with_participant(): void
    {
        $admin = $this->createAdminUser();

        $createResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 50.00,
            ]));

        $expenseId = $createResponse->json('data.expense.id');
        $this->postParticipantsSplit($admin, $expenseId, 2, 50.00);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/expenses/{$expenseId}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'meta',
                'data' => [
                    'expense' => [
                        'id', 'description', 'total_amount', 'status',
                        'charges' => [
                            '*' => ['id', 'amount', 'status', 'participant'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_pix_key_is_required(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', [
                'description' => 'Test',
                'total_amount' => 50.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('pix_key');
    }

    public function test_admin_can_patch_expense(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());
        $create->assertStatus(201);

        $expenseId = $create->json('data.expense.id');
        $this->postParticipantsSplit($admin, $expenseId, 2, 100.00);

        $newDue = now()->addDays(10)->format('Y-m-d');

        $patch = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/expenses/{$expenseId}", [
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
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 90.00,
            ]));

        $expenseId = $create->json('data.expense.id');

        $add = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => $admin->name, 'phone' => '11900000001', 'amount' => 30],
                    ['name' => 'Participant 1', 'phone' => '11900000002', 'amount' => 30],
                    ['name' => 'Novo Membro', 'phone' => '11988887777', 'amount' => 30],
                ],
            ]);

        $add->assertOk();
        $this->assertDatabaseCount('charges', 3);
    }

    public function test_add_participants_rejects_duplicate_phone_in_payload(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 60.00,
            ]));
        $create->assertStatus(201);
        $expenseId = $create->json('data.expense.id');

        $dup = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => 'Um', 'phone' => '11900000001', 'amount' => 30],
                    ['name' => 'Dois mesmo tel', 'phone' => '11900000001', 'amount' => 30],
                ],
            ]);

        $dup->assertStatus(422)
            ->assertJsonPath('message', 'Já existe um participante com este telefone nesta despesa.')
            ->assertJsonPath('code', 'DUPLICATED_PARTICIPANT_PHONE');

        $this->assertDatabaseCount('charges', 0);
    }

    public function test_add_participants_rejects_phone_already_registered_on_expense(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 60.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => 'Um', 'phone' => '11900000001', 'amount' => 30],
                    ['name' => 'Dois', 'phone' => '11900000002', 'amount' => 30],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseCount('charges', 2);

        $again = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => 'Duplicado', 'phone' => '11900000002', 'amount' => 30],
                ],
            ]);

        $again->assertStatus(422)
            ->assertJsonPath('message', 'Já existe um participante com este telefone nesta despesa.')
            ->assertJsonPath('code', 'DUPLICATED_PARTICIPANT_PHONE');

        $this->assertDatabaseCount('charges', 2);
    }

    public function test_add_participants_second_batch_accepts_sum_existing_plus_new_above_total(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 100.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => 'A', 'phone' => '11900000001', 'amount' => 50],
                    ['name' => 'B', 'phone' => '11900000002', 'amount' => 50],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseCount('charges', 2);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => 'C', 'phone' => '11900000003', 'amount' => 10],
                ],
            ]);

        $response->assertOk();

        $charges = \App\Models\Charge::where('expense_id', $expenseId)->orderBy('id')->get();
        $this->assertCount(3, $charges);
        $this->assertEquals(110.0, round($charges->sum(fn ($c) => (float) $c->amount), 2));
        $this->assertEquals(10.0, (float) $charges[2]->amount);
    }

    public function test_non_creator_cannot_patch_expense(): void
    {
        $admin = User::factory()->create();
        $otherUser = User::factory()->create();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());
        $expenseId = $create->json('data.expense.id');

        $patch = $this->actingAs($otherUser, 'sanctum')
            ->patchJson("/api/v1/expenses/{$expenseId}", [
                'description' => 'Hack',
                'total_amount' => 50.00,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
                'pix_key' => '11999999999',
            ]);

        $patch->assertStatus(403);
    }

    public function test_expenses_index_returns_charges_with_participants(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 40.00,
            ]));
        $create->assertStatus(201);
        $expenseId = $create->json('data.expense.id');
        $this->postParticipantsSplit($admin, $expenseId, 2, 40.00);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/expenses');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'expenses' => [
                        '*' => [
                            'id',
                            'description',
                            'charges' => [
                                '*' => [
                                    'id',
                                    'amount',
                                    'status',
                                    'participant' => ['name', 'phone'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $expense = $response->json('data.expenses.0');
        $this->assertArrayNotHasKey('manage_token', $expense);
        $this->assertCount(2, $expense['charges']);
        $this->assertNotEmpty($expense['charges'][0]['participant']['name']);
    }

    public function test_expenses_index_only_returns_own_created_expenses(): void
    {
        $admin = $this->createAdminUser();
        $stranger = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());

        $response = $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/v1/expenses');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(0, $response->json('data.expenses') ?? []);
    }

    public function test_expenses_index_is_paginated(): void
    {
        $admin = $this->createAdminUser();

        Expense::factory()->count(18)->create([
            'created_by' => $admin->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/expenses?per_page=15');

        $response->assertOk()
            ->assertJsonCount(15, 'data.expenses')
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.pagination.per_page', 15)
            ->assertJsonPath('meta.pagination.total', 18);
    }

    public function test_delete_expense_removes_payment_proof_file(): void
    {
        Storage::fake('local');

        $admin = $this->createAdminUser();
        $expense = Expense::factory()->create([
            'created_by' => $admin->id,
            'status' => 'open',
        ]);
        $participant = ExpenseParticipant::factory()->create([
            'expense_id' => $expense->id,
            'phone_normalized' => '11999999999',
        ]);
        $charge = \App\Models\Charge::factory()->create([
            'expense_id' => $expense->id,
            'expense_participant_id' => $participant->id,
            'status' => 'pending',
        ]);
        $path = "payment-proofs/expense-{$expense->id}/11999999999-20260502-184530001.jpg";
        Storage::disk('local')->put($path, 'jpeg-bytes');

        PaymentProof::factory()->create([
            'charge_id' => $charge->id,
            'file_path' => $path,
            'original_filename' => 'proof.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/expenses/{$expense->id}")
            ->assertOk();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('payment_proofs', ['charge_id' => $charge->id]);
    }

    public function test_user_cannot_show_other_users_expense(): void
    {
        $admin = $this->createAdminUser();
        $stranger = User::factory()->create();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/expenses/{$expenseId}")
            ->assertStatus(403);
    }

    public function test_admin_can_delete_expense_when_all_charges_pending(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 60.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => $admin->name, 'phone' => '11900000001', 'amount' => 30],
                    ['name' => 'Participant 1', 'phone' => '11900000002', 'amount' => 30],
                ],
            ])
            ->assertOk();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/expenses/{$expenseId}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('expenses', ['id' => $expenseId]);
        $this->assertDatabaseCount('charges', 0);
    }

    public function test_cannot_delete_expense_when_charge_not_pending(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 60.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => $admin->name, 'phone' => '11900000001', 'amount' => 30],
                    ['name' => 'Participant 1', 'phone' => '11900000002', 'amount' => 30],
                ],
            ])
            ->assertOk();

        $charge = \App\Models\Charge::where('expense_id', $expenseId)->first();
        $charge->update(['status' => 'proof_sent']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/expenses/{$expenseId}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'EXPENSE_CANNOT_BE_DELETED');

        $this->assertDatabaseHas('expenses', ['id' => $expenseId]);
    }

    public function test_non_creator_cannot_delete_expense(): void
    {
        $admin = User::factory()->create();
        $otherUser = User::factory()->create();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => $admin->name, 'phone' => '11900000001', 'amount' => 50],
                    ['name' => $otherUser->name, 'phone' => '11900000002', 'amount' => 50],
                ],
            ])
            ->assertOk();

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/v1/expenses/{$expenseId}")
            ->assertStatus(403);
    }

    public function test_add_participants_rejects_sum_mismatch(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 90.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => $admin->name, 'phone' => '11900000001', 'amount' => 30],
                    ['name' => 'Participant 1', 'phone' => '11900000002', 'amount' => 30],
                    ['name' => 'Novo Membro', 'phone' => '11988887777', 'amount' => 20],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Os valores dos participantes ainda não fecham o total da cobrança.')
            ->assertJsonPath('code', 'PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL');
    }

    public function test_update_participant_accepts_sum_above_total(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 100.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => 'A', 'phone' => '11900000001', 'amount' => 50],
                    ['name' => 'B', 'phone' => '11900000002', 'amount' => 50],
                ],
            ])
            ->assertOk();

        $participantId = ExpenseParticipant::query()
            ->where('expense_id', $expenseId)
            ->where('phone_normalized', '11900000001')
            ->value('id');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/expenses/{$expenseId}/participants/{$participantId}", [
                'amount' => 70,
            ])
            ->assertOk();

        $charges = \App\Models\Charge::where('expense_id', $expenseId)->orderBy('id')->get();
        $this->assertEquals(120.0, round($charges->sum(fn ($c) => (float) $c->amount), 2));
        $this->assertEquals(70.0, (float) $charges[0]->amount);
    }

    public function test_update_participant_rejects_sum_below_total(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 100.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => 'A', 'phone' => '11900000001', 'amount' => 50],
                    ['name' => 'B', 'phone' => '11900000002', 'amount' => 50],
                ],
            ])
            ->assertOk();

        $participantId = ExpenseParticipant::query()
            ->where('expense_id', $expenseId)
            ->where('phone_normalized', '11900000001')
            ->value('id');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/expenses/{$expenseId}/participants/{$participantId}", [
                'amount' => 40,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Os valores dos participantes ainda não fecham o total da cobrança.')
            ->assertJsonPath('code', 'PARTICIPANT_TOTAL_BELOW_EXPENSE_TOTAL');
    }

    public function test_patch_expense_rejected_when_closed(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload());
        $expenseId = $create->json('data.expense.id');

        Expense::query()->whereKey($expenseId)->update(['status' => 'closed']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/expenses/{$expenseId}", [
                'description' => 'Alterado',
                'total_amount' => 100.00,
                'due_date' => now()->addDays(5)->format('Y-m-d'),
                'pix_key' => '11999999999',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', ExpenseClosedPolicy::CODE)
            ->assertJsonPath('message', ExpenseClosedPolicy::MESSAGE);
    }

    public function test_delete_expense_rejected_when_closed(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 60.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => $admin->name, 'phone' => '11900000001', 'amount' => 30],
                    ['name' => 'Participant 1', 'phone' => '11900000002', 'amount' => 30],
                ],
            ])
            ->assertOk();

        Expense::query()->whereKey($expenseId)->update(['status' => 'closed']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/expenses/{$expenseId}")
            ->assertStatus(422)
            ->assertJsonPath('code', ExpenseClosedPolicy::CODE)
            ->assertJsonPath('message', ExpenseClosedPolicy::MESSAGE);
    }

    public function test_add_participants_rejected_when_closed(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 60.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        Expense::query()->whereKey($expenseId)->update(['status' => 'closed']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => $admin->name, 'phone' => '11900000001', 'amount' => 30],
                    ['name' => 'Participant 1', 'phone' => '11900000002', 'amount' => 30],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', ExpenseClosedPolicy::CODE)
            ->assertJsonPath('message', ExpenseClosedPolicy::MESSAGE);
    }

    public function test_update_participant_rejected_when_closed(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 60.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => $admin->name, 'phone' => '11900000001', 'amount' => 30],
                    ['name' => 'Participant 1', 'phone' => '11900000002', 'amount' => 30],
                ],
            ])
            ->assertOk();

        $participantId = ExpenseParticipant::query()->where('expense_id', $expenseId)->first()->id;

        Expense::query()->whereKey($expenseId)->update(['status' => 'closed']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/expenses/{$expenseId}/participants/{$participantId}", [
                'name' => 'Novo nome',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', ExpenseClosedPolicy::CODE)
            ->assertJsonPath('message', ExpenseClosedPolicy::MESSAGE);
    }

    public function test_remove_participant_rejected_when_closed(): void
    {
        $admin = $this->createAdminUser();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/expenses', $this->expensePayload([
                'total_amount' => 90.00,
            ]));
        $expenseId = $create->json('data.expense.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/expenses/{$expenseId}/participants", [
                'participants' => [
                    ['name' => $admin->name, 'phone' => '11900000001', 'amount' => 30],
                    ['name' => 'Participant 1', 'phone' => '11900000002', 'amount' => 30],
                    ['name' => 'Participant 2', 'phone' => '11900000003', 'amount' => 30],
                ],
            ])
            ->assertOk();

        $participantId = ExpenseParticipant::query()
            ->where('expense_id', $expenseId)
            ->where('phone_normalized', '11900000003')
            ->first()
            ->id;

        Expense::query()->whereKey($expenseId)->update(['status' => 'closed']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/expenses/{$expenseId}/participants/{$participantId}")
            ->assertStatus(422)
            ->assertJsonPath('code', ExpenseClosedPolicy::CODE)
            ->assertJsonPath('message', ExpenseClosedPolicy::MESSAGE);
    }
}
