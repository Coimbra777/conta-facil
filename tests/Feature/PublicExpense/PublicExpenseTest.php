<?php

namespace Tests\Feature\PublicExpense;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function createExpenseWithCharges(): array
    {
        $admin = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $admin->id]);

        $member1 = TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $admin->id,
            'name' => 'Joao Admin',
            'phone' => '11000000001',
            'email' => $admin->email,
            'role' => 'admin',
        ]);

        $member2 = TeamMember::create([
            'team_id' => $team->id,
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'role' => 'member',
        ]);

        $expense = Expense::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'owner_name' => 'Dono Teste',
            'owner_phone' => '11988887777',
            'description' => 'Churrasco',
            'total_amount' => 100.00,
            'amount_per_member' => 50.00,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
            'pix_qr_code' => base64_encode('fake-qr'),
            'status' => 'open',
            'public_hash' => 'test-hash-123',
            'manage_token' => 'manage-token-secret',
        ]);

        $charge1 = Charge::create([
            'expense_id' => $expense->id,
            'team_member_id' => $member1->id,
            'amount' => 50.00,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        $charge2 = Charge::create([
            'expense_id' => $expense->id,
            'team_member_id' => $member2->id,
            'amount' => 50.00,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        return [$expense, $charge1, $charge2, $admin, $team];
    }

    /**
     * @return array{0: Expense, 1: Charge}
     */
    private function createPublicExpenseWithOneParticipant500(): array
    {
        $admin = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $admin->id]);

        $member = TeamMember::create([
            'team_id' => $team->id,
            'name' => 'Unico Participante',
            'phone' => '11000000001',
            'role' => 'member',
        ]);

        $expense = Expense::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'owner_name' => 'Dono Teste',
            'owner_phone' => '11988887777',
            'description' => 'Rateio',
            'total_amount' => 500.00,
            'amount_per_member' => 500.00,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
            'pix_qr_code' => base64_encode('fake-qr'),
            'status' => 'open',
            'public_hash' => 'test-hash-500',
            'manage_token' => 'manage-token-secret',
        ]);

        $charge = Charge::create([
            'expense_id' => $expense->id,
            'team_member_id' => $member->id,
            'amount' => 500.00,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        return [$expense, $charge];
    }

    public function test_public_expense_page_returns_data(): void
    {
        [$expense] = $this->createExpenseWithCharges();

        $response = $this->getJson('/api/v1/public/expenses/test-hash-123');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expense.description', 'Churrasco')
            ->assertJsonPath('data.expense.pix_key', '11999999999')
            ->assertJsonPath('data.expense.can_manage', false)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'expense' => ['id', 'description', 'total_amount', 'amount', 'amount_per_member', 'pix_key', 'pix_qr_code', 'participants', 'can_manage'],
                ],
                'meta',
            ]);
    }

    public function test_public_expense_with_manage_returns_members_and_can_manage(): void
    {
        $this->createExpenseWithCharges();

        $response = $this->getJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('manage-token-secret'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expense.can_manage', true)
            ->assertJsonPath('data.expense.owner_phone', '11988887777')
            ->assertJsonCount(2, 'data.expense.members')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'expense' => ['members', 'owner_name', 'amount_per_member'],
                ],
                'meta',
            ]);
    }

    public function test_invalid_hash_returns_404(): void
    {
        $response = $this->getJson('/api/v1/public/expenses/invalid-hash');

        $response->assertStatus(404);
    }

    public function test_submit_proof_creates_payment_proof_record(): void
    {
        Storage::fake('local');
        $this->createExpenseWithCharges();

        $file = UploadedFile::fake()->create('comprovante.jpg', 100, 'image/jpeg');
        $response = $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'proof' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'proof_sent');

        $charge = Charge::query()
            ->whereHas('teamMember', fn ($q) => $q->where('phone', '11000000002'))
            ->first();
        $this->assertNotNull($charge);
        $this->assertDatabaseHas('payment_proofs', [
            'charge_id' => $charge->id,
            'original_filename' => 'comprovante.jpg',
            'status' => 'pending',
        ]);
    }

    public function test_submit_proof_rejects_invalid_file_type(): void
    {
        Storage::fake('local');
        $this->createExpenseWithCharges();

        $file = UploadedFile::fake()->create('doc.txt', 100, 'text/plain');

        $response = $this->postJson('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'proof' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('proof');
    }

    public function test_submit_proof_rejects_oversized_file(): void
    {
        Storage::fake('local');
        $this->createExpenseWithCharges();

        $file = UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg');

        $response = $this->postJson('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'proof' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('proof');
    }

    public function test_public_validate_charge_with_manage_token(): void
    {
        Storage::fake('local');

        [, , $charge2] = $this->createExpenseWithCharges();

        $file = UploadedFile::fake()->create('comprovante.jpg', 100, 'image/jpeg');
        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'proof' => $file,
        ])->assertStatus(201);

        $response = $this->patchJson("/api/v1/public/charges/{$charge2->id}/validate", [
            'manage_token' => 'manage-token-secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.charge.status', 'validated');
    }

    public function test_validate_participant_exact_match_returns_status_and_can_submit(): void
    {
        $this->createExpenseWithCharges();

        $this->postJson('/api/v1/public/expenses/test-hash-123/validate-participant', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.can_submit_proof', true)
            ->assertJsonPath('message', 'Você ainda não enviou comprovante.');
    }

    public function test_validate_participant_wrong_name_returns_422(): void
    {
        $this->createExpenseWithCharges();

        $this->postJson('/api/v1/public/expenses/test-hash-123/validate-participant', [
            'name' => 'Maria Errada',
            'phone' => '11000000002',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Participante não encontrado nesta despesa.');
    }

    public function test_submit_proof_uploads_and_sets_proof_sent(): void
    {
        Storage::fake('local');
        $this->createExpenseWithCharges();

        $this->postJson('/api/v1/public/expenses/test-hash-123/validate-participant', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
        ])->assertOk();

        $file = UploadedFile::fake()->create('comp.jpg', 100, 'image/jpeg');
        $response = $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'proof' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'proof_sent')
            ->assertJsonPath('message', 'Comprovante enviado. Aguardando aprovação do responsável.');

        $this->assertDatabaseHas('team_members', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
        ]);

        $charge = Charge::query()->where('expense_id', Expense::where('public_hash', 'test-hash-123')->value('id'))
            ->whereHas('teamMember', fn ($q) => $q->where('phone', '11000000002'))
            ->first();
        $this->assertNotNull($charge);
        $this->assertSame('proof_sent', $charge->status);
        $this->assertDatabaseHas('payment_proofs', [
            'charge_id' => $charge->id,
        ]);
    }

    public function test_submit_proof_twice_second_returns_422(): void
    {
        Storage::fake('local');
        $this->createExpenseWithCharges();

        $payload = [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'proof' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg'),
        ];
        $this->postJson('/api/v1/public/expenses/test-hash-123/validate-participant', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
        ])->assertOk();

        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', $payload)->assertStatus(201);

        $this->postJson('/api/v1/public/expenses/test-hash-123/validate-participant', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'proof_sent')
            ->assertJsonPath('data.can_submit_proof', false);

        $payload['proof'] = UploadedFile::fake()->create('b.jpg', 100, 'image/jpeg');
        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Comprovante já enviado.')
            ->assertJsonPath('code', 'PROOF_ALREADY_SENT')
            ->assertJsonPath('errors.status', 'proof_sent');
    }

    public function test_submit_proof_rejects_when_already_validated(): void
    {
        Storage::fake('local');
        [, , $charge2] = $this->createExpenseWithCharges();

        $file = UploadedFile::fake()->create('c.jpg', 100, 'image/jpeg');
        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'proof' => $file,
        ])->assertStatus(201);
        $this->patchJson("/api/v1/public/charges/{$charge2->id}/validate", [
            'manage_token' => 'manage-token-secret',
        ])->assertOk();

        $response = $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'proof' => UploadedFile::fake()->create('n.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Pagamento já confirmado.')
            ->assertJsonPath('code', 'CHARGE_ALREADY_PAID')
            ->assertJsonPath('errors.status', 'validated');
    }

    public function test_submit_proof_does_not_match_wrong_exact_name(): void
    {
        Storage::fake('local');
        $this->createExpenseWithCharges();

        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Atualizada',
            'phone' => '11000000002',
            'proof' => UploadedFile::fake()->create('comp.jpg', 100, 'image/jpeg'),
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Participante não encontrado nesta despesa.');

        $this->assertDatabaseHas('team_members', [
            'phone' => '11000000002',
            'name' => 'Maria Silva',
        ]);
    }

    public function test_public_patch_expense_with_manage_token(): void
    {
        $this->createExpenseWithCharges();
        $newDue = now()->addDays(7)->format('Y-m-d');

        $response = $this->patchJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('manage-token-secret'), [
            'description' => 'Churrasco atualizado',
            'amount' => 120,
            'due_date' => $newDue,
            'pix_key' => 'pix@novo.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.expense.description', 'Churrasco atualizado')
            ->assertJsonPath('data.expense.pix_key', 'pix@novo.com');

        $this->assertDatabaseHas('expenses', [
            'public_hash' => 'test-hash-123',
            'description' => 'Churrasco atualizado',
        ]);
    }

    public function test_public_patch_expense_forbidden_without_token(): void
    {
        $this->createExpenseWithCharges();

        $this->patchJson('/api/v1/public/expenses/test-hash-123', [
            'description' => 'X',
            'amount' => 99,
            'due_date' => now()->format('Y-m-d'),
            'pix_key' => 'k',
        ])->assertForbidden();
    }

    public function test_add_public_participants_creates_charges(): void
    {
        $this->createExpenseWithCharges();

        $this->patchJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('manage-token-secret'), [
            'description' => 'Churrasco',
            'amount' => 150,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
        ]);

        $response = $this->postJson('/api/v1/public/expenses/test-hash-123/participants?manage='.urlencode('manage-token-secret'), [
            'participants' => [
                ['name' => 'Zeca Novo', 'phone' => '11977776666'],
            ],
        ]);

        $response->assertStatus(201);
        $expenseId = Expense::where('public_hash', 'test-hash-123')->value('id');
        $this->assertEquals(3, Charge::where('expense_id', $expenseId)->count());

        $charges = Charge::where('expense_id', $expenseId)->orderBy('id')->get();
        $this->assertEquals(150.0, round($charges->sum(fn ($c) => (float) $c->amount), 2));
    }

    public function test_add_public_participants_redistributes_full_total_evenly(): void
    {
        [$expense] = $this->createPublicExpenseWithOneParticipant500();

        $this->postJson('/api/v1/public/expenses/test-hash-500/participants?manage='.urlencode('manage-token-secret'), [
            'participants' => [
                ['name' => 'Segundo', 'phone' => '11977776666'],
            ],
        ])->assertStatus(201);

        $charges = Charge::where('expense_id', $expense->id)->orderBy('id')->get();
        $this->assertCount(2, $charges);
        $this->assertEquals(500.0, round($charges->sum(fn ($c) => (float) $c->amount), 2));
        $this->assertEquals(250.0, (float) $charges[0]->amount);
        $this->assertEquals(250.0, (float) $charges[1]->amount);
    }

    public function test_add_public_participants_rejects_when_payments_in_progress(): void
    {
        $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'proof_sent']);

        $this->postJson('/api/v1/public/expenses/test-hash-123/participants?manage='.urlencode('manage-token-secret'), [
            'participants' => [
                ['name' => 'Novo', 'phone' => '11977776666'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não é possível redistribuir valores pois já existem pagamentos em andamento.');
    }

    public function test_add_public_participants_rejects_duplicate_phone(): void
    {
        $this->createExpenseWithCharges();
        $this->patchJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('manage-token-secret'), [
            'description' => 'Churrasco',
            'amount' => 150,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
        ]);

        $this->postJson('/api/v1/public/expenses/test-hash-123/participants?manage='.urlencode('manage-token-secret'), [
            'participants' => [
                ['name' => 'Duplicado', 'phone' => '11000000002'],
            ],
        ])->assertStatus(422);
    }

    public function test_close_expense_forbidden_without_manage_token(): void
    {
        $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'validated']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_close_expense_rejects_when_any_charge_not_validated(): void
    {
        $this->createExpenseWithCharges();

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'So e possivel finalizar quando todos os participantes estiverem com pagamento validado.');
    }

    public function test_close_expense_rejects_when_charge_is_proof_sent(): void
    {
        [, $charge1, $charge2] = $this->createExpenseWithCharges();
        $charge1->update(['status' => 'validated']);
        $charge2->update(['status' => 'proof_sent']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertStatus(422);
    }

    public function test_close_expense_rejects_when_charge_is_rejected(): void
    {
        [, $charge1, $charge2] = $this->createExpenseWithCharges();
        $charge1->update(['status' => 'validated']);
        $charge2->update(['status' => 'rejected']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertStatus(422);
    }

    public function test_close_expense_succeeds_when_all_charges_validated(): void
    {
        $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'validated']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertOk()
            ->assertJsonPath('data.expense.status', 'closed')
            ->assertJsonPath('data.expense.is_closed', true);

        $this->assertDatabaseHas('expenses', [
            'public_hash' => 'test-hash-123',
            'status' => 'closed',
        ]);
    }

    public function test_close_expense_returns_422_when_already_closed(): void
    {
        $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'validated']);
        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertOk();

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta despesa ja foi finalizada.');
    }

    public function test_public_actions_blocked_after_expense_closed(): void
    {
        Storage::fake('local');
        [, , $charge2] = $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'validated']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertOk();

        $msg = 'Esta despesa foi finalizada e nao aceita mais alteracoes.';

        $this->patchJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('manage-token-secret'), [
            'description' => 'X',
            'amount' => 99,
            'due_date' => now()->format('Y-m-d'),
            'pix_key' => 'k',
        ])->assertStatus(422)->assertJsonPath('message', $msg);

        $this->postJson('/api/v1/public/expenses/test-hash-123/participants?manage='.urlencode('manage-token-secret'), [
            'participants' => [['name' => 'Novo', 'phone' => '11911112222']],
        ])->assertStatus(422)->assertJsonPath('message', $msg);

        $file = UploadedFile::fake()->create('c.jpg', 100, 'image/jpeg');
        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
            'proof' => $file,
        ])->assertStatus(422)->assertJsonPath('message', $msg);

        $this->patchJson("/api/v1/public/charges/{$charge2->id}/validate", [
            'manage_token' => 'manage-token-secret',
        ])->assertStatus(422)->assertJsonPath('message', $msg);

        $this->patchJson("/api/v1/public/charges/{$charge2->id}/reject", [
            'manage_token' => 'manage-token-secret',
        ])->assertStatus(422)->assertJsonPath('message', $msg);

        $this->postJson('/api/v1/public/expenses/test-hash-123/validate-participant', [
            'name' => 'Maria Silva',
            'phone' => '11000000002',
        ])->assertStatus(422)->assertJsonPath('message', $msg);
    }
}
