<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $next_user_id = 1;

        return [
            'user_id' => $next_user_id++,
            // 'balance' => $this->faker->randomFloat(2, 0, 1000),
            'balance' => $next_user_id * 100,
        ];
    }
}
