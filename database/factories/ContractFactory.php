<?php

namespace Database\Factories;

use App\ContractStatus;
use App\Models\Client;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-3 months', 'now');
        $endDate = fake()->boolean(70) ? fake()->dateTimeBetween($startDate, '+12 months') : null;

        return [
            'client_id' => Client::factory(),
            'name' => 'Contrato '.fake()->word(),
            'description' => fake()->optional()->paragraph(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'monthly_hours' => fake()->optional(0.8)->randomFloat(2, 5, 80),
            'hourly_rate' => fake()->optional(0.8)->randomFloat(2, 50, 300),
            'domain_rate' => fake()->optional(0.8)->randomFloat(2, 10, 100),
            'status' => fake()->randomElement(ContractStatus::cases()),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
