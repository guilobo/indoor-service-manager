<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Proposal;
use App\ProposalStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'title' => 'Proposta '.fake()->word(),
            'description' => fake()->optional()->paragraph(),
            'hours' => fake()->randomFloat(2, 1, 80),
            'hourly_rate' => fake()->randomFloat(2, 50, 300),
            'status' => ProposalStatus::Pending,
            'proposal_file' => null,
            'attachments' => [],
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
