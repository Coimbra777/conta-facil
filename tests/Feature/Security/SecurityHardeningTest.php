<?php

namespace Tests\Feature\Security;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\Team;
use App\Models\TeamMember;
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
            'description' => 'Teste segurança',
            'total_amount' => 100.00,
            'amount_per_member' => 100.00,
            'due_date' => now()->addDay()->format('Y-m-d'),
            'pix_key' => '11999999999',
            'status' => 'open',
        ]);
        $expense->forceFill([
            'public_hash' => 'sec-hash-1',
            'manage_token' => 'good-manage-token',
        ])->save();

        Charge::create([
            'expense_id' => $expense->id,
            'team_member_id' => $member->id,
            'user_id' => $member->user_id,
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
            ])->assertStatus(401);
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
        $team = Team::factory()->create(['owner_id' => $admin->id]);

        $member = TeamMember::create([
            'team_id' => $team->id,
            'user_id' => null,
            'name' => 'Participante',
            'phone' => '11000000002',
            'role' => 'member',
        ]);

        $expense = Expense::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'description' => 'Rateio',
            'total_amount' => 100.00,
            'amount_per_member' => 100.00,
            'due_date' => now()->addDay()->format('Y-m-d'),
            'pix_key' => '11999999999',
            'status' => 'open',
        ]);
        $expense->forceFill([
            'public_hash' => 'sec-hash-proof',
            'manage_token' => 'manage-x',
        ])->save();

        Charge::create([
            'expense_id' => $expense->id,
            'team_member_id' => $member->id,
            'user_id' => null,
            'description' => $expense->description,
            'amount' => 100.00,
            'due_date' => $expense->due_date,
            'status' => 'pending',
        ]);

        $file = UploadedFile::fake()->create('malware.exe', 50);

        $this->post('/api/v1/public/expenses/sec-hash-proof/submit-proof', [
            'name' => $member->name,
            'phone' => $member->phone,
            'proof' => $file,
        ])->assertStatus(422);
    }

    public function test_api_responses_include_security_headers(): void
    {
        $admin = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $admin->id]);
        TeamMember::create([
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
            'description' => 'X',
            'total_amount' => 10.00,
            'amount_per_member' => 10.00,
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
