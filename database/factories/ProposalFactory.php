<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Proposal;
use App\ProposalBillingType;
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
            'billing_type' => ProposalBillingType::Hourly,
            'hours' => fake()->randomFloat(2, 1, 80),
            'hourly_rate' => fake()->randomFloat(2, 50, 300),
            'fixed_value' => null,
            'status' => ProposalStatus::Pending,
            'proposal_file' => null,
            'attachments' => [],
            'notes' => fake()->optional()->paragraph(),
        ];
    }

    public function fixedValue(): static
    {
        return $this->state(fn (): array => [
            'billing_type' => ProposalBillingType::Fixed,
            'hours' => null,
            'hourly_rate' => null,
            'fixed_value' => fake()->randomFloat(2, 500, 10000),
        ]);
    }
}
