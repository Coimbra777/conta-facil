<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'owner_name' => null,
            'owner_phone' => null,
            'description' => fake()->sentence(),
            'total_amount' => fake()->randomFloat(2, 10, 5000),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'status' => 'open',
            'amount_per_participant' => null,
            'pix_key' => null,
            'pix_qr_code' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }

    public function withPix(): static
    {
        return $this->state(fn (array $attributes) => [
            'pix_key' => '11999999999',
            'pix_qr_code' => base64_encode('fake-qr'),
        ]);
    }
}
