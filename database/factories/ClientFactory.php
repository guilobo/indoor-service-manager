<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company_name' => fake()->optional()->company(),
            'document' => fake()->unique()->numerify('##############'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'whatsapp' => fake()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
