<?php

namespace Tests\Feature\PublicExpense;

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorePublicExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_expense_participants_and_charges(): void
    {
        $response = $this->postJson('/api/public/expenses', [
            'owner_name' => 'Ana',
            'owner_phone' => '11988776655',
            'description' => 'Churras',
            'amount' => 100,
            'pix_key' => 'ana@email.com',
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'participants' => [
                ['name' => 'Beto', 'phone' => '11911112222'],
                ['name' => 'Cris', 'phone' => '11933334444'],
            ],
        ]);

        $response->assertCreated()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'meta',
                'data' => [
                    'expense' => [
                        'id',
                        'public_hash',
                        'participant_url',
                        'manage_url',
                        'manage_token',
                        'manage_path',
                    ],
                ],
            ]);

        $managePath = $response->json('data.expense.manage_path');
        $this->assertIsString($managePath);
        $this->assertStringStartsWith('/p/', $managePath);
        $this->assertStringNotContainsString('manage=', $managePath);

        $manageUrl = $response->json('data.expense.manage_url');
        $this->assertIsString($manageUrl);
        $this->assertStringContainsString('#manage=', $manageUrl);

        $participantUrl = $response->json('data.expense.participant_url');
        $this->assertIsString($participantUrl);
        $this->assertStringNotContainsString('manage=', $participantUrl);

        $this->assertDatabaseHas('expenses', [
            'description' => 'Churras',
            'owner_name' => 'Ana',
            'owner_phone' => '11988776655',
        ]);

        $expense = Expense::first();
        $this->assertEquals(2, $expense->charges()->count());
        $this->assertEquals(2, ExpenseParticipant::where('expense_id', $expense->id)->count());
        $this->assertTrue(
            $expense->charges()->whereNull('expense_participant_id')->doesntExist()
        );
    }

    public function test_accepts_participants_text_instead_of_array(): void
    {
        $text = "Ze 61911112222\nLu 61933334444";

        $response = $this->postJson('/api/public/expenses', [
            'owner_name' => 'Ana',
            'owner_phone' => '11988776655',
            'description' => 'Pizza',
            'amount' => 80,
            'pix_key' => 'pix',
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'participants_text' => $text,
        ]);

        $response->assertCreated();
        $this->assertEquals(2, Expense::first()->charges()->count());
    }

    public function test_include_owner_as_participant_adds_owner_to_split(): void
    {
        $response = $this->postJson('/api/public/expenses', [
            'owner_name' => 'Ana',
            'owner_phone' => '11988776655',
            'description' => 'Churras',
            'amount' => 100,
            'pix_key' => 'ana@email.com',
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'include_owner_as_participant' => true,
            'participants' => [
                ['name' => 'Beto', 'phone' => '11911112222'],
            ],
        ]);

        $response->assertCreated();
        $expense = Expense::first();
        $this->assertEquals(2, $expense->charges()->count());

        $phones = $expense->charges()->with('expenseParticipant')->get()->map(
            fn ($c) => $c->expenseParticipant?->phone
        )->map(fn ($p) => preg_replace('/\D+/', '', (string) $p))->sort()->values()->all();
        $this->assertEquals(['11911112222', '11988776655'], $phones);

        $total = round((float) $expense->charges()->sum('amount'), 2);
        $this->assertEquals(100.0, $total);
    }

    public function test_include_owner_as_participant_does_not_duplicate_phone(): void
    {
        $response = $this->postJson('/api/public/expenses', [
            'owner_name' => 'Ana',
            'owner_phone' => '11988776655',
            'description' => 'Churras',
            'amount' => 100,
            'pix_key' => 'ana@email.com',
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'include_owner_as_participant' => true,
            'participants' => [
                ['name' => 'Ana Mesmo', 'phone' => '11988776655'],
                ['name' => 'Beto', 'phone' => '11911112222'],
            ],
        ]);

        $response->assertCreated();
        $expense = Expense::first();
        $this->assertEquals(2, $expense->charges()->count());
        $this->assertEquals(2, ExpenseParticipant::where('expense_id', $expense->id)->count());
    }

    public function test_public_validate_participant_matches_expense_participant(): void
    {
        $this->postJson('/api/public/expenses', [
            'owner_name' => 'Ana',
            'owner_phone' => '11988776655',
            'description' => 'Churras',
            'amount' => 100,
            'pix_key' => 'ana@email.com',
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'participants' => [
                ['name' => 'Beto', 'phone' => '11911112222'],
            ],
        ])->assertCreated();

        $hash = Expense::first()->public_hash;
        $this->postJson("/api/v1/public/expenses/{$hash}/validate-participant", [
            'name' => 'Beto',
            'phone' => '11911112222',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.can_submit_proof', true)
            ->assertJsonPath('data.amount', 100);
    }

    public function test_same_normalized_phone_on_two_expenses_creates_two_participants(): void
    {
        $due = now()->addDays(5)->format('Y-m-d');
        $common = ['name' => 'Beto', 'phone' => '11911112222'];

        $this->postJson('/api/public/expenses', [
            'owner_name' => 'Ana',
            'owner_phone' => '11988776655',
            'description' => 'Churras A',
            'amount' => 100,
            'pix_key' => 'pix',
            'due_date' => $due,
            'participants' => [$common],
        ])->assertCreated();

        $this->postJson('/api/public/expenses', [
            'owner_name' => 'Ana',
            'owner_phone' => '11988776655',
            'description' => 'Churras B',
            'amount' => 200,
            'pix_key' => 'pix',
            'due_date' => $due,
            'participants' => [$common],
        ])->assertCreated();

        $normalized = preg_replace('/\D+/', '', $common['phone']);
        $this->assertEquals(
            2,
            ExpenseParticipant::where('phone_normalized', $normalized)->count()
        );
    }
}
