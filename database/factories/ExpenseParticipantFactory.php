<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseParticipant;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseParticipant>
 */
class ExpenseParticipantFactory extends Factory
{
    protected $model = ExpenseParticipant::class;

    public function definition(): array
    {
        $phone = fake()->numerify('119########');

        return [
            'expense_id' => Expense::factory(),
            'name' => fake()->name(),
            'phone' => $phone,
            'phone_normalized' => PhoneNormalizer::digits($phone),
            'email' => null,
            'amount' => fake()->randomFloat(2, 10, 500),
            'metadata' => null,
        ];
    }
}
