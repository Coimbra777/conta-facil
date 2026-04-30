<?php

namespace Tests\Feature\Security;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_close_forbidden_with_invalid_manage_token(): void
    {
        $admin = User::factory()->create();

        $expense = Expense::create([
            'created_by' => $admin->id,
            'description' => 'Teste segurança',
            'total_amount' => 100.00,
            'amount_per_participant' => 100.00,
            'due_date' => now()->addDay()->format('Y-m-d'),
            'pix_key' => '11999999999',
            'status' => 'open',
        ]);
        $expense->forceFill([
            'public_hash' => 'sec-hash-1',
            'manage_token' => 'good-manage-token',
        ])->save();

        $ep = ExpenseParticipant::create([
            'expense_id' => $expense->id,
            'name' => 'Admin',
            'phone' => '11000000001',
            'phone_normalized' => '11000000001',
            'amount' => 100.00,
        ]);

        Charge::create([
            'expense_id' => $expense->id,
            'expense_participant_id' => $ep->id,
            'description' => $expense->description,
            'amount' => 100.00,
            'due_date' => $expense->due_date,
            'status' => 'validated',
        ]);

        $this->patchJson('/api/v1/public/expenses/sec-hash-1/close?manage='.urlencode('token-invalido'))
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Você não tem permissão para realizar esta ação.',
            );
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'naoexiste@example.com',
                'password' => 'qualquercoisa',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'naoexiste@example.com',
            'password' => 'qualquercoisa',
        ])->assertStatus(429);
    }

    public function test_submit_proof_rejects_disallowed_extension(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();

        $expense = Expense::create([
            'created_by' => $admin->id,
            'description' => 'Rateio',
            'total_amount' => 100.00,
            'amount_per_participant' => 100.00,
            'due_date' => now()->addDay()->format('Y-m-d'),
            'pix_key' => '11999999999',
            'status' => 'open',
        ]);
        $expense->forceFill([
            'public_hash' => 'sec-hash-proof',
            'manage_token' => 'manage-x',
        ])->save();

        $ep = ExpenseParticipant::create([
            'expense_id' => $expense->id,
            'name' => 'Participante',
            'phone' => '11000000002',
            'phone_normalized' => '11000000002',
            'amount' => 100.00,
        ]);

        Charge::create([
            'expense_id' => $expense->id,
            'expense_participant_id' => $ep->id,
            'description' => $expense->description,
            'amount' => 100.00,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        $file = UploadedFile::fake()->create('malware.exe', 50);

        $this->post('/api/v1/public/expenses/sec-hash-proof/submit-proof', [
            'name' => $ep->name,
            'phone' => $ep->phone,
            'proof' => $file,
        ])->assertStatus(422);
    }

    public function test_api_responses_include_security_headers(): void
    {
        $admin = User::factory()->create();

        $expense = Expense::create([
            'created_by' => $admin->id,
            'description' => 'X',
            'total_amount' => 10.00,
            'amount_per_participant' => 10.00,
            'due_date' => now()->addDay()->format('Y-m-d'),
            'pix_key' => 'pix',
            'status' => 'open',
        ]);
        $expense->forceFill([
            'public_hash' => 'sec-headers',
            'manage_token' => 'm',
        ])->save();

        $response = $this->getJson('/api/v1/public/expenses/sec-headers');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }
}
