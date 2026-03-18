<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Reunião',
                'Manutenção',
                'Deploy',
                'Suporte',
                'Configuração de servidor',
            ]),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
