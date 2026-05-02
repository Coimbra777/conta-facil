<?php

namespace Tests\Feature\PaymentValidation;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\PaymentProof;
use App\Services\ExpenseService;
use App\Models\User;
use App\Support\ExpenseClosedPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ProofUploadFixture;
use Tests\TestCase;

class PaymentValidationTest extends TestCase
{
    use RefreshDatabase;

    private function proofPathForExpense(int $expenseId, string $phone, string $suffix = '000'): string
    {
        return "payment-proofs/expense-{$expenseId}/{$phone}-20260502-184530{$suffix}.jpg";
    }

    /**
     * @return array{0: User, 1: Expense, 2: Charge, 3: Charge}
     */
    private function createExpenseSetup(): array
    {
        $admin = User::factory()->create();

        $expense = Expense::create([
            'created_by' => $admin->id,
            'description' => 'Test expense',
            'total_amount' => 100.00,
            'amount_per_participant' => 50.00,
            'due_date' => now()->addDays(3)->format('Y-m-d'),
            'pix_key' => '11999999999',
            'status' => 'open',
        ]);
        $expense->forceFill([
            'public_hash' => 'val-hash-123',
            'manage_token' => 'val-manage-token',
        ])->save();

        $epAdmin = ExpenseParticipant::create([
            'expense_id' => $expense->id,
            'name' => $admin->name,
            'phone' => '11900000001',
            'phone_normalized' => '11900000001',
            'amount' => 50.00,
        ]);

        $epMaria = ExpenseParticipant::create([
            'expense_id' => $expense->id,
            'name' => 'Maria',
            'phone' => '11900000002',
            'phone_normalized' => '11900000002',
            'amount' => 50.00,
        ]);

        $charge1 = Charge::create([
            'expense_id' => $expense->id,
            'expense_participant_id' => $epAdmin->id,
            'amount' => 50.00,
            'due_date' => $expense->due_date,
            'status' => 'proof_sent',
        ]);

        $charge2 = Charge::create([
            'expense_id' => $expense->id,
            'expense_participant_id' => $epMaria->id,
            'amount' => 50.00,
            'due_date' => $expense->due_date,
            'status' => 'proof_sent',
        ]);

        PaymentProof::create([
            'charge_id' => $charge1->id,
            'file_path' => $this->proofPathForExpense($expense->id, '11900000001', '001'),
            'original_filename' => 'comprovante1.jpg',
            'mime_type' => 'image/jpeg',
            'status' => 'pending',
        ]);

        PaymentProof::create([
            'charge_id' => $charge2->id,
            'file_path' => $this->proofPathForExpense($expense->id, '11900000002', '002'),
            'original_filename' => 'comprovante2.jpg',
            'mime_type' => 'image/jpeg',
            'status' => 'pending',
        ]);

        return [$admin, $expense, $charge1, $charge2];
    }

    public function test_admin_can_validate_charge(): void
    {
        [$admin, , $charge1] = $this->createExpenseSetup();

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge1->id}/validate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.charge.status', 'validated');

        $this->assertDatabaseHas('charges', [
            'id' => $charge1->id,
            'status' => 'validated',
        ]);
        $this->assertNotNull($charge1->fresh()->paid_at);
    }

    public function test_admin_can_reject_charge(): void
    {
        [$admin, , , $charge2] = $this->createExpenseSetup();

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge2->id}/reject", [
                'reason' => 'Valor divergente.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.charge.status', 'rejected')
            ->assertJsonPath('data.charge.rejection_reason', 'Valor divergente.');

        $this->assertDatabaseHas('charges', [
            'id' => $charge2->id,
            'status' => 'rejected',
        ]);
    }

    public function test_admin_reject_charge_requires_reason(): void
    {
        [$admin, , , $charge2] = $this->createExpenseSetup();

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge2->id}/reject", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_expense_closes_when_all_charges_validated_and_deletes_proof_files(): void
    {
        Storage::fake('local');

        [$admin, $expense, $charge1, $charge2] = $this->createExpenseSetup();
        $path1 = $this->proofPathForExpense($expense->id, '11900000001', '001');
        $path2 = $this->proofPathForExpense($expense->id, '11900000002', '002');

        Storage::disk('local')->put($path1, 'proof-1');
        Storage::disk('local')->put($path2, 'proof-2');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge1->id}/validate");

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge2->id}/validate");

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => 'closed',
        ]);
        $this->assertDatabaseHas('payment_proofs', [
            'charge_id' => $charge1->id,
            'file_path' => null,
        ]);
        $this->assertDatabaseHas('payment_proofs', [
            'charge_id' => $charge2->id,
            'file_path' => null,
        ]);
        Storage::disk('local')->assertMissing($path1);
        Storage::disk('local')->assertMissing($path2);
    }

    public function test_expense_stays_open_until_all_charges_validated(): void
    {
        [$admin, $expense, $charge1] = $this->createExpenseSetup();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge1->id}/validate");

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => 'open',
        ]);
    }

    public function test_non_admin_cannot_validate(): void
    {
        [, , $charge1] = $this->createExpenseSetup();

        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge1->id}/validate");

        $response->assertStatus(403);
    }

    public function test_cannot_validate_pending_charge(): void
    {
        [$admin, $expense] = $this->createExpenseSetup();

        $epExtra = ExpenseParticipant::create([
            'expense_id' => $expense->id,
            'name' => 'Pedro',
            'phone' => '11900000003',
            'phone_normalized' => '11900000003',
            'amount' => 25.00,
        ]);

        $pendingCharge = Charge::create([
            'expense_id' => $expense->id,
            'expense_participant_id' => $epExtra->id,
            'amount' => 25.00,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$pendingCharge->id}/validate");

        $response->assertStatus(422);
    }

    public function test_rejected_charge_can_upload_new_proof(): void
    {
        Storage::fake('local');

        [$admin, $expense, , $charge2] = $this->createExpenseSetup();
        $stalePath = $this->proofPathForExpense($expense->id, '11900000002', '002');
        Storage::disk('local')->put($stalePath, 'stale-proof');

        // Reject first
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge2->id}/reject", [
                'reason' => 'Motivo do teste.',
            ]);

        $this->assertDatabaseHas('charges', ['id' => $charge2->id, 'status' => 'rejected']);

        $file = ProofUploadFixture::jpegUploadedFile('new_proof.jpg');
        $response = $this->post('/api/v1/public/expenses/val-hash-123/submit-proof', [
            'name' => 'Maria',
            'phone' => '11900000002',
            'proof' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'proof_sent');

        Storage::disk('local')->assertMissing($stalePath);
        $proof = PaymentProof::query()->where('charge_id', $charge2->id)->latest('id')->firstOrFail();
        $this->assertMatchesRegularExpression(
            '/^payment-proofs\/expense-'.$expense->id.'\/11900000002-\d{8}-\d{9}\.jpg$/',
            (string) $proof->file_path,
        );
    }

    public function test_expense_owner_can_view_latest_proof_inline(): void
    {
        Storage::fake('local');

        [$admin, , $charge1] = $this->createExpenseSetup();
        $proofPath = $this->proofPathForExpense($charge1->expense_id, '11900000001', '001');

        Storage::disk('local')->put($proofPath, 'fake-jpeg-bytes');

        $response = $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/charges/{$charge1->id}/proofs/latest/view");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsStringIgnoringCase(
            'inline',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_non_owner_cannot_view_latest_proof_inline(): void
    {
        Storage::fake('local');

        [, , $charge1] = $this->createExpenseSetup();
        $proofPath = $this->proofPathForExpense($charge1->expense_id, '11900000001', '001');

        Storage::disk('local')->put($proofPath, 'x');

        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')
            ->get("/api/v1/charges/{$charge1->id}/proofs/latest/view")
            ->assertStatus(403);
    }

    public function test_view_latest_proof_returns_404_when_file_missing_on_disk(): void
    {
        Storage::fake('local');

        [$admin, , $charge1] = $this->createExpenseSetup();

        $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/charges/{$charge1->id}/proofs/latest/view")
            ->assertStatus(404)
            ->assertJsonPath('code', 'PROOF_NOT_FOUND')
            ->assertJsonPath('message', 'Comprovante não encontrado.');
    }

    public function test_validate_charge_returns_expense_closed_when_expense_closed(): void
    {
        [$admin, $expense, $charge1] = $this->createExpenseSetup();

        $expense->update(['status' => 'closed']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge1->id}/validate")
            ->assertStatus(422)
            ->assertJsonPath('code', ExpenseClosedPolicy::CODE)
            ->assertJsonPath('message', ExpenseClosedPolicy::MESSAGE);
    }

    public function test_reject_charge_returns_expense_closed_when_expense_closed(): void
    {
        [$admin, $expense, , $charge2] = $this->createExpenseSetup();

        $expense->update(['status' => 'closed']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge2->id}/reject", [
                'reason' => 'Teste.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', ExpenseClosedPolicy::CODE)
            ->assertJsonPath('message', ExpenseClosedPolicy::MESSAGE);
    }

    public function test_closing_expense_cleanup_is_idempotent(): void
    {
        Storage::fake('local');

        [, $expense, $charge1, $charge2] = $this->createExpenseSetup();
        $path1 = $this->proofPathForExpense($expense->id, '11900000001', '001');
        $path2 = $this->proofPathForExpense($expense->id, '11900000002', '002');

        Storage::disk('local')->put($path1, 'a');
        Storage::disk('local')->put($path2, 'b');

        $service = app(ExpenseService::class);

        $service->closeExpense($expense);
        $service->closeExpense($expense->fresh());

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => 'closed',
        ]);
        $this->assertDatabaseHas('payment_proofs', [
            'charge_id' => $charge1->id,
            'file_path' => null,
        ]);
        $this->assertDatabaseHas('payment_proofs', [
            'charge_id' => $charge2->id,
            'file_path' => null,
        ]);
        Storage::disk('local')->assertMissing($path1);
        Storage::disk('local')->assertMissing($path2);
    }

    public function test_owner_cannot_view_proof_when_expense_closed(): void
    {
        Storage::fake('local');

        [$admin, $expense, $charge1, $charge2] = $this->createExpenseSetup();
        $path1 = $this->proofPathForExpense($expense->id, '11900000001', '001');
        $path2 = $this->proofPathForExpense($expense->id, '11900000002', '002');

        Storage::disk('local')->put($path1, 'a');
        Storage::disk('local')->put($path2, 'b');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge1->id}/validate")
            ->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge2->id}/validate")
            ->assertOk();

        $expense->refresh();
        $this->assertSame('closed', $expense->status);

        $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/charges/{$charge1->id}/proofs/latest/view")
            ->assertStatus(404)
            ->assertJsonPath('code', 'PROOF_REMOVED_AFTER_EXPENSE_CLOSED')
            ->assertJsonPath('message', 'Comprovantes excluídos após a finalização da cobrança.');
    }

    public function test_expense_owner_can_download_latest_proof_before_expense_closed(): void
    {
        Storage::fake('local');

        [$admin, , $charge1] = $this->createExpenseSetup();
        $proofPath = $this->proofPathForExpense($charge1->expense_id, '11900000001', '001');

        Storage::disk('local')->put($proofPath, 'fake-jpeg-bytes');

        $response = $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/charges/{$charge1->id}/proof");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsStringIgnoringCase(
            'attachment',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_owner_cannot_download_proof_when_expense_closed(): void
    {
        Storage::fake('local');

        [$admin, $expense, $charge1, $charge2] = $this->createExpenseSetup();
        $path1 = $this->proofPathForExpense($expense->id, '11900000001', '001');
        $path2 = $this->proofPathForExpense($expense->id, '11900000002', '002');

        Storage::disk('local')->put($path1, 'a');
        Storage::disk('local')->put($path2, 'b');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge1->id}/validate")
            ->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge2->id}/validate")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/charges/{$charge1->id}/proof")
            ->assertStatus(404)
            ->assertJsonPath('code', 'PROOF_REMOVED_AFTER_EXPENSE_CLOSED')
            ->assertJsonPath('message', 'Comprovantes excluídos após a finalização da cobrança.');
    }
}
