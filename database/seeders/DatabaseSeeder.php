<?php

namespace Database\Seeders;

use App\ContractStatus;
use App\DomainStatus;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Domain;
use App\Models\Proposal;
use App\Models\Service;
use App\Models\User;
use App\ProposalStatus;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@indoor.local',
            'role' => UserRole::Admin,
            'password' => 'password',
        ]);

        User::factory()->create([
            'name' => 'Operador',
            'email' => 'operador@indoor.local',
            'role' => UserRole::Operator,
            'password' => 'password',
        ]);

        Service::factory()->createMany([
            ['name' => 'Reunião', 'description' => 'Alinhamentos e reuniões com o cliente.'],
            ['name' => 'Manutenção', 'description' => 'Correções, ajustes e sustentação.'],
            ['name' => 'Deploy', 'description' => 'Publicações e entregas em produção.'],
            ['name' => 'Suporte', 'description' => 'Atendimentos operacionais e suporte técnico.'],
            ['name' => 'Configuração de servidor', 'description' => 'Configuração de infraestrutura e serviços.'],
        ]);

        $client = Client::factory()->create([
            'name' => 'Mariana Costa',
            'company_name' => 'Costa Indoor Tech',
            'document' => '12345678000190',
            'email' => 'contato@costaindoor.com',
        ]);

        $contract = Contract::factory()->create([
            'client_id' => $client->id,
            'name' => 'Contrato Principal 2026',
            'monthly_hours' => 20,
            'hourly_rate' => 180,
            'domain_rate' => 35,
            'status' => ContractStatus::Active,
        ]);

        $proposal = Proposal::factory()->create([
            'client_id' => $client->id,
            'title' => 'Proposta de melhorias 2026',
            'hours' => 12,
            'hourly_rate' => 180,
            'status' => ProposalStatus::Pending,
        ]);

        Domain::factory()->count(2)->create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'status' => DomainStatus::Active,
        ]);

        Activity::factory()->count(6)->create([
            'contract_id' => $contract->id,
            'service_id' => Service::query()->inRandomOrder()->value('id'),
        ]);

        Activity::factory()->create([
            'contract_id' => null,
            'proposal_id' => $proposal->id,
            'title' => 'Elaboração da proposta de melhorias',
            'service_id' => Service::query()->inRandomOrder()->value('id'),
        ]);
    }
}
