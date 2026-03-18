<?php

namespace Database\Factories;

use App\DomainStatus;
use App\Models\Client;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
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
            'contract_id' => null,
            'domain_name' => fake()->domainName(),
            'status' => fake()->randomElement(DomainStatus::cases()),
            'notes' => fake()->optional()->sentence(),
            'credentials' => [
                'usuario' => fake()->userName(),
                'senha' => fake()->password(),
            ],
            'ftp_host' => fake()->ipv4(),
            'ftp_user' => fake()->userName(),
            'ftp_password' => fake()->password(),
            'hosting' => fake()->company(),
            'panel_url' => fake()->url(),
            'email_accounts' => [
                [
                    'email' => fake()->safeEmail(),
                    'password' => fake()->optional()->password(),
                ],
                [
                    'email' => fake()->safeEmail(),
                    'password' => fake()->optional()->password(),
                ],
            ],
            'other_data' => [
                'observacoes' => fake()->sentence(),
            ],
        ];
    }
}
