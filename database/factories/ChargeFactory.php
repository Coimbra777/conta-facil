<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\Expense;
use App\Models\ExpenseParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Charge>
 */
class ChargeFactory extends Factory
{
    protected $model = Charge::class;

    public function definition(): array
    {
        return [
            'expense_participant_id' => null,
            'expense_id' => Expense::factory(),
            'description' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 5, 1000),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'status' => 'pending',
            'paid_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Charge $charge): void {
            if ($charge->expense_participant_id !== null) {
                return;
            }

            $participant = ExpenseParticipant::factory()->create([
                'expense_id' => $charge->expense_id,
            ]);

            $charge->expense_participant_id = $participant->id;
        });
    }

    public function proofSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'proof_sent',
        ]);
    }

    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'validated',
            'paid_at' => now(),
        ]);
    }
}
