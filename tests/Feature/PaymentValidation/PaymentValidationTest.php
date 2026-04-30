<?php

namespace Tests\Feature\PaymentValidation;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ProofUploadFixture;
use Tests\TestCase;

class PaymentValidationTest extends TestCase
{
    use RefreshDatabase;

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
            'phone' => '11000000001',
            'phone_normalized' => '11000000001',
            'amount' => 50.00,
        ]);

        $epMaria = ExpenseParticipant::create([
            'expense_id' => $expense->id,
            'name' => 'Maria',
            'phone' => '11000000002',
            'phone_normalized' => '11000000002',
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
            'file_path' => 'payment-proofs/1/proof.jpg',
            'original_filename' => 'comprovante1.jpg',
            'mime_type' => 'image/jpeg',
            'status' => 'pending',
        ]);

        PaymentProof::create([
            'charge_id' => $charge2->id,
            'file_path' => 'payment-proofs/2/proof.jpg',
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

    public function test_expense_closes_when_all_charges_validated(): void
    {
        [$admin, $expense, $charge1, $charge2] = $this->createExpenseSetup();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge1->id}/validate");

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge2->id}/validate");

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => 'closed',
        ]);
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
            'phone' => '11000000003',
            'phone_normalized' => '11000000003',
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

        [$admin, , , $charge2] = $this->createExpenseSetup();

        // Reject first
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/charges/{$charge2->id}/reject", [
                'reason' => 'Motivo do teste.',
            ]);

        $this->assertDatabaseHas('charges', ['id' => $charge2->id, 'status' => 'rejected']);

        $file = ProofUploadFixture::jpegUploadedFile('new_proof.jpg');
        $response = $this->post('/api/v1/public/expenses/val-hash-123/submit-proof', [
            'name' => 'Maria',
            'phone' => '11000000002',
            'proof' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'proof_sent');
    }
}
