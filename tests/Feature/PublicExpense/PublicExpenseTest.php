<?php

namespace Tests\Feature\PublicExpense;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\PaymentProof;
use App\Models\User;
use App\Support\ExpenseClosedPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ProofUploadFixture;
use Tests\TestCase;

class PublicExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function assertProofStoredForExpense(string $path, int $expenseId, string $phone): void
    {
        $this->assertMatchesRegularExpression(
            '/^payment-proofs\/expense-'.$expenseId.'\/'.$phone.'-\d{8}-\d{9}\.(jpg|png|pdf)$/',
            $path,
        );
        $this->assertSame("payment-proofs/expense-{$expenseId}", dirname($path));
    }

    private function createExpenseWithCharges(): array
    {
        $admin = User::factory()->create();

        $expense = Expense::create([
            'created_by' => $admin->id,
            'owner_name' => 'Dono Teste',
            'owner_phone' => '11988887777',
            'description' => 'Churrasco',
            'total_amount' => 100.00,
            'amount_per_participant' => 50.00,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
            'pix_qr_code' => base64_encode('fake-qr'),
            'status' => 'open',
        ]);
        $expense->forceFill([
            'public_hash' => 'test-hash-123',
            'manage_token' => 'manage-token-secret',
        ])->save();

        $ep1 = ExpenseParticipant::create([
            'expense_id' => $expense->id,
            'name' => 'Joao Admin',
            'phone' => '11900000001',
            'phone_normalized' => '11900000001',
            'amount' => 50.00,
        ]);

        $ep2 = ExpenseParticipant::create([
            'expense_id' => $expense->id,
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'phone_normalized' => '11900000002',
            'amount' => 50.00,
        ]);

        $charge1 = Charge::create([
            'expense_id' => $expense->id,
            'expense_participant_id' => $ep1->id,
            'amount' => 50.00,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        $charge2 = Charge::create([
            'expense_id' => $expense->id,
            'expense_participant_id' => $ep2->id,
            'amount' => 50.00,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        return [$expense, $charge1, $charge2, $admin];
    }

    /**
     * @return array{0: Expense, 1: Charge}
     */
    private function createPublicExpenseWithOneParticipant500(): array
    {
        $admin = User::factory()->create();

        $expense = Expense::create([
            'created_by' => $admin->id,
            'owner_name' => 'Dono Teste',
            'owner_phone' => '11988887777',
            'description' => 'Rateio',
            'total_amount' => 500.00,
            'amount_per_participant' => 500.00,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
            'pix_qr_code' => base64_encode('fake-qr'),
            'status' => 'open',
        ]);
        $expense->forceFill([
            'public_hash' => 'test-hash-500',
            'manage_token' => 'manage-token-secret',
        ])->save();

        $ep = ExpenseParticipant::create([
            'expense_id' => $expense->id,
            'name' => 'Unico Participante',
            'phone' => '11900000001',
            'phone_normalized' => '11900000001',
            'amount' => 500.00,
        ]);

        $charge = Charge::create([
            'expense_id' => $expense->id,
            'expense_participant_id' => $ep->id,
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
            ->assertJsonPath('data.expense.participants_total_count', 2)
            ->assertJsonPath('data.expense.validated_charges_count', 0)
            ->assertJsonPath('data.expense.open_charges_count', 2)
            ->assertJsonMissingPath('data.expense.participants')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'expense' => [
                        'id',
                        'description',
                        'total_amount',
                        'amount',
                        'amount_per_participant',
                        'average_amount_per_participant',
                        'pix_key',
                        'pix_qr_code',
                        'participants_total_count',
                        'validated_charges_count',
                        'open_charges_count',
                        'can_manage',
                    ],
                ],
                'meta',
            ]);
    }

    public function test_public_expense_with_manage_returns_participants_and_can_manage(): void
    {
        $this->createExpenseWithCharges();

        $response = $this->getJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('manage-token-secret'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expense.can_manage', true)
            ->assertJsonPath('data.expense.owner_phone', '11988887777')
            ->assertJsonCount(2, 'data.expense.participants')
            ->assertJsonPath('data.expense.average_amount_per_participant', '50.00')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'expense' => ['participants', 'owner_name', 'amount_per_participant'],
                ],
                'meta',
            ]);
    }

    public function test_public_expense_with_manage_header_returns_participants_and_can_manage(): void
    {
        $this->createExpenseWithCharges();

        $response = $this->getJson('/api/v1/public/expenses/test-hash-123', [
            'X-Manage-Token' => 'manage-token-secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.expense.can_manage', true)
            ->assertJsonCount(2, 'data.expense.participants');
    }

    public function test_manage_token_header_takes_precedence_over_query_string(): void
    {
        $this->createExpenseWithCharges();

        $response = $this->getJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('wrong-token'), [
            'X-Manage-Token' => 'manage-token-secret',
        ]);

        $response->assertOk()->assertJsonPath('data.expense.can_manage', true);
    }

    public function test_invalid_hash_returns_404(): void
    {
        $response = $this->getJson('/api/v1/public/expenses/invalid-hash');

        $response->assertStatus(404);
    }

    public function test_submit_proof_creates_payment_proof_record(): void
    {
        Storage::fake('local');
        [$expense, , $charge] = $this->createExpenseWithCharges();

        $file = ProofUploadFixture::jpegUploadedFile('comprovante.jpg');
        $response = $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'proof_sent');

        $proof = PaymentProof::query()
            ->where('charge_id', $charge->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertProofStoredForExpense((string) $proof->file_path, $expense->id, '11900000002');
        Storage::disk('local')->assertExists($proof->file_path);
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
            'phone' => '11900000002',
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
            'phone' => '11900000002',
            'proof' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('proof');
    }

    public function test_public_validate_charge_with_manage_token(): void
    {
        Storage::fake('local');

        [, , $charge2] = $this->createExpenseWithCharges();

        $file = ProofUploadFixture::jpegUploadedFile('comprovante.jpg');
        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
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
            'phone' => '11900000002',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.can_submit_proof', true)
            ->assertJsonPath('data.amount', 50)
            ->assertJsonPath('message', 'Você ainda não enviou comprovante.');
    }

    public function test_validate_participant_wrong_name_returns_422(): void
    {
        $this->createExpenseWithCharges();

        $this->postJson('/api/v1/public/expenses/test-hash-123/validate-participant', [
            'name' => 'Maria Errada',
            'phone' => '11900000002',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Participante não encontrado nesta despesa.');
    }

    public function test_submit_proof_uploads_and_sets_proof_sent(): void
    {
        Storage::fake('local');
        [$expense, , $charge] = $this->createExpenseWithCharges();

        $this->postJson('/api/v1/public/expenses/test-hash-123/validate-participant', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
        ])->assertOk();

        $file = ProofUploadFixture::jpegUploadedFile('comp.jpg');
        $response = $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'proof_sent')
            ->assertJsonPath('message', 'Comprovante enviado. Aguardando aprovação do responsável.');

        $this->assertDatabaseHas('expense_participants', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
        ]);

        $this->assertSame('proof_sent', $charge->fresh()->status);
        $proof = PaymentProof::query()
            ->where('charge_id', $charge->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertProofStoredForExpense((string) $proof->file_path, $expense->id, '11900000002');
        $this->assertStringNotContainsString('/'.$charge->id.'/', (string) $proof->file_path);
    }

    public function test_submit_proof_twice_second_returns_422(): void
    {
        Storage::fake('local');
        $this->createExpenseWithCharges();

        $payload = [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => ProofUploadFixture::jpegUploadedFile('a.jpg'),
        ];
        $this->postJson('/api/v1/public/expenses/test-hash-123/validate-participant', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
        ])->assertOk();

        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', $payload)->assertStatus(201);

        $this->postJson('/api/v1/public/expenses/test-hash-123/validate-participant', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'proof_sent')
            ->assertJsonPath('data.can_submit_proof', false);

        $payload['proof'] = ProofUploadFixture::jpegUploadedFile('b.jpg');
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

        $file = ProofUploadFixture::jpegUploadedFile('c.jpg');
        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => $file,
        ])->assertStatus(201);
        $this->patchJson("/api/v1/public/charges/{$charge2->id}/validate", [
            'manage_token' => 'manage-token-secret',
        ])->assertOk();

        $response = $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => ProofUploadFixture::jpegUploadedFile('n.jpg'),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Pagamento já confirmado.')
            ->assertJsonPath('code', 'PARTICIPANT_ALREADY_VALIDATED')
            ->assertJsonPath('errors.status', 'validated');
    }

    public function test_resubmitting_proof_after_rejection_removes_old_file(): void
    {
        Storage::fake('local');
        [$expense, , $charge2] = $this->createExpenseWithCharges();

        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => ProofUploadFixture::jpegUploadedFile('primeiro.jpg'),
        ])->assertStatus(201);

        $firstProof = PaymentProof::query()->where('charge_id', $charge2->id)->latest()->firstOrFail();
        $firstPath = $firstProof->file_path;

        $this->patchJson("/api/v1/public/charges/{$charge2->id}/reject", [
            'manage_token' => 'manage-token-secret',
            'reason' => 'Arquivo ilegível.',
        ])->assertOk();

        Storage::disk('local')->assertExists($firstPath);

        $this->get("/api/v1/public/charges/{$charge2->id}/proofs/latest/view", [
            'X-Manage-Token' => 'manage-token-secret',
        ])->assertOk()->assertHeader('content-type', 'image/jpeg');

        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => ProofUploadFixture::jpegUploadedFile('segundo.jpg'),
        ])->assertStatus(201);

        Storage::disk('local')->assertMissing($firstPath);
        $this->assertDatabaseCount('payment_proofs', 1);
        $latestPath = (string) PaymentProof::query()->firstOrFail()->file_path;
        $this->assertNotSame($firstPath, $latestPath);
        $this->assertProofStoredForExpense($latestPath, $expense->id, '11900000002');
    }

    public function test_submit_proof_does_not_match_wrong_exact_name(): void
    {
        Storage::fake('local');
        $this->createExpenseWithCharges();

        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Atualizada',
            'phone' => '11900000002',
            'proof' => ProofUploadFixture::jpegUploadedFile('comp.jpg'),
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Participante não encontrado nesta despesa.');

        $this->assertDatabaseHas('expense_participants', [
            'phone' => '11900000002',
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
                ['name' => 'Duplicado', 'phone' => '11900000002'],
            ],
        ])->assertStatus(422);
    }

    public function test_close_expense_forbidden_without_manage_token(): void
    {
        $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'validated']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close')
            ->assertForbidden()
            ->assertJsonPath('message', 'Token de gestão inválido.')
            ->assertJsonPath('code', 'INVALID_MANAGE_TOKEN');
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
        Storage::fake('local');
        [, $charge1, $charge2] = $this->createExpenseWithCharges();

        $file = ProofUploadFixture::jpegUploadedFile('manual-close.jpg');
        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => $file,
        ])->assertStatus(201);

        $proof = PaymentProof::query()
            ->where('charge_id', $charge2->id)
            ->latest('id')
            ->firstOrFail();

        Storage::disk('local')->assertExists($proof->file_path);

        $charge1->update(['status' => 'validated']);
        $charge2->update(['status' => 'validated']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertOk()
            ->assertJsonPath('data.expense.status', 'closed')
            ->assertJsonPath('data.expense.is_closed', true)
            ->assertJsonPath('data.expense.participants.1.has_proof', false);

        $this->assertDatabaseHas('expenses', [
            'public_hash' => 'test-hash-123',
            'status' => 'closed',
        ]);
        $this->assertDatabaseHas('payment_proofs', [
            'id' => $proof->id,
            'file_path' => null,
        ]);
        Storage::disk('local')->assertMissing($proof->file_path);
    }

    public function test_close_expense_returns_422_when_already_closed(): void
    {
        $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'validated']);
        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertOk();

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertStatus(422)
            ->assertJsonPath('message', ExpenseClosedPolicy::MESSAGE)
            ->assertJsonPath('code', ExpenseClosedPolicy::CODE);
    }

    public function test_public_actions_blocked_after_expense_closed(): void
    {
        Storage::fake('local');
        [, , $charge2] = $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'validated']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertOk();

        $msg = ExpenseClosedPolicy::MESSAGE;

        $this->patchJson('/api/v1/public/expenses/test-hash-123?manage='.urlencode('manage-token-secret'), [
            'description' => 'X',
            'amount' => 99,
            'due_date' => now()->format('Y-m-d'),
            'pix_key' => 'k',
        ])->assertStatus(422)->assertJsonPath('message', $msg)->assertJsonPath('code', ExpenseClosedPolicy::CODE);

        $this->postJson('/api/v1/public/expenses/test-hash-123/participants?manage='.urlencode('manage-token-secret'), [
            'participants' => [['name' => 'Novo', 'phone' => '11911112222']],
        ])->assertStatus(422)->assertJsonPath('message', $msg)->assertJsonPath('code', ExpenseClosedPolicy::CODE);

        $file = ProofUploadFixture::jpegUploadedFile('c.jpg');
        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => $file,
        ])->assertStatus(422)->assertJsonPath('message', $msg)->assertJsonPath('code', ExpenseClosedPolicy::CODE);

        $this->patchJson("/api/v1/public/charges/{$charge2->id}/validate", [
            'manage_token' => 'manage-token-secret',
        ])->assertStatus(422)->assertJsonPath('message', $msg)->assertJsonPath('code', ExpenseClosedPolicy::CODE);

        $this->patchJson("/api/v1/public/charges/{$charge2->id}/reject", [
            'manage_token' => 'manage-token-secret',
            'reason' => 'Comprovante ilegível.',
        ])->assertStatus(422)->assertJsonPath('message', $msg)->assertJsonPath('code', ExpenseClosedPolicy::CODE);

        $this->postJson('/api/v1/public/expenses/test-hash-123/validate-participant', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
        ])->assertStatus(422)->assertJsonPath('message', $msg)->assertJsonPath('code', ExpenseClosedPolicy::CODE);
    }

    public function test_public_get_expense_when_closed_includes_read_only_flags(): void
    {
        $this->createExpenseWithCharges();
        Charge::query()->update(['status' => 'validated']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertOk();

        $this->getJson('/api/v1/public/expenses/test-hash-123')
            ->assertOk()
            ->assertJsonPath('data.expense.status', 'closed')
            ->assertJsonPath('data.expense.is_closed', true);
    }

    public function test_public_manage_can_view_latest_proof_inline(): void
    {
        Storage::fake('local');
        [$expense, , $charge] = $this->createExpenseWithCharges();

        $this->postJson('/api/v1/public/expenses/test-hash-123/validate-participant', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
        ])->assertOk();

        $file = ProofUploadFixture::jpegUploadedFile('inline-view.jpg');
        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => $file,
        ])->assertStatus(201);

        $proof = PaymentProof::query()
            ->where('charge_id', $charge->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertProofStoredForExpense((string) $proof->file_path, $expense->id, '11900000002');

        $view = $this->get("/api/v1/public/charges/{$charge->id}/proofs/latest/view", [
            'X-Manage-Token' => 'manage-token-secret',
        ]);

        $view->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->assertStringContainsStringIgnoringCase(
            'inline',
            (string) $view->headers->get('Content-Disposition'),
        );

        $this->get("/api/v1/public/charges/{$charge->id}/proofs/latest/view")
            ->assertStatus(403);
    }

    public function test_public_manage_can_download_latest_proof_before_expense_closed(): void
    {
        Storage::fake('local');
        [, , $charge] = $this->createExpenseWithCharges();

        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => ProofUploadFixture::jpegUploadedFile('download-proof.jpg'),
        ])->assertStatus(201);

        $download = $this->get("/api/v1/public/charges/{$charge->id}/proof", [
            'X-Manage-Token' => 'manage-token-secret',
        ]);

        $download->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->assertStringContainsStringIgnoringCase(
            'attachment',
            (string) $download->headers->get('Content-Disposition'),
        );
    }

    public function test_public_manage_cannot_view_proof_after_expense_closed(): void
    {
        Storage::fake('local');
        [, $charge1, $charge2] = $this->createExpenseWithCharges();

        $file = ProofUploadFixture::jpegUploadedFile('closed-proof.jpg');
        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => $file,
        ])->assertStatus(201);

        $charge1->update(['status' => 'validated']);
        $charge2->update(['status' => 'validated']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertOk();

        $this->get("/api/v1/public/charges/{$charge2->id}/proofs/latest/view", [
            'X-Manage-Token' => 'manage-token-secret',
        ])->assertStatus(404)
            ->assertJsonPath('code', 'PROOF_REMOVED_AFTER_EXPENSE_CLOSED')
            ->assertJsonPath('message', 'Comprovantes excluídos após a finalização da cobrança.');
    }

    public function test_public_manage_cannot_download_proof_after_expense_closed(): void
    {
        Storage::fake('local');
        [, $charge1, $charge2] = $this->createExpenseWithCharges();

        $this->post('/api/v1/public/expenses/test-hash-123/submit-proof', [
            'name' => 'Maria Silva',
            'phone' => '11900000002',
            'proof' => ProofUploadFixture::jpegUploadedFile('closed-download.jpg'),
        ])->assertStatus(201);

        $charge1->update(['status' => 'validated']);
        $charge2->update(['status' => 'validated']);

        $this->patchJson('/api/v1/public/expenses/test-hash-123/close?manage='.urlencode('manage-token-secret'))
            ->assertOk();

        $this->get("/api/v1/public/charges/{$charge2->id}/proof", [
            'X-Manage-Token' => 'manage-token-secret',
        ])->assertStatus(404)
            ->assertJsonPath('code', 'PROOF_REMOVED_AFTER_EXPENSE_CLOSED')
            ->assertJsonPath('message', 'Comprovantes excluídos após a finalização da cobrança.');
    }
}
