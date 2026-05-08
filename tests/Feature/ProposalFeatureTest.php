<?php

use App\Models\Activity;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Proposal;
use App\ProposalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('creates proposals as pending by default and calculates their estimated value', function () {
    $client = Client::factory()->create();

    $proposal = Proposal::query()->create([
        'client_id' => $client->id,
        'title' => 'Nova proposta',
        'hours' => 12.5,
        'hourly_rate' => 180,
    ]);

    expect($proposal->fresh()->status)->toBe(ProposalStatus::Pending)
        ->and($proposal->fresh()->estimated_value)->toBe(2250.0);
});

it('normalizes proposal attachments when upload paths arrive as arrays', function () {
    expect(Proposal::normalizeAttachmentItems([
        [
            'title' => 'Aceite do cliente',
            'path' => ['proposals/attachments/aceite.png'],
        ],
    ]))->toBe([
        [
            'title' => 'Aceite do cliente',
            'path' => 'proposals/attachments/aceite.png',
            'url' => Storage::disk('public')->url('proposals/attachments/aceite.png'),
        ],
    ]);
});

it('allows activities linked only to proposals', function () {
    $proposal = Proposal::factory()->create();

    $activity = Activity::factory()->create([
        'contract_id' => null,
        'proposal_id' => $proposal->id,
    ]);

    expect($activity->proposal->is($proposal))->toBeTrue()
        ->and($activity->contract)->toBeNull()
        ->and($activity->source_label)->toBe('Proposta')
        ->and($activity->source_name)->toBe($proposal->title)
        ->and($activity->client_name)->toBe($proposal->client->name);
});

it('keeps activities linked only to contracts working', function () {
    $contract = Contract::factory()->create();

    $activity = Activity::factory()->create([
        'contract_id' => $contract->id,
        'proposal_id' => null,
    ]);

    expect($activity->contract->is($contract))->toBeTrue()
        ->and($activity->proposal)->toBeNull()
        ->and($activity->source_label)->toBe('Contrato')
        ->and($activity->source_name)->toBe($contract->name)
        ->and($activity->client_name)->toBe($contract->client->name);
});

it('filters activities by client through proposals', function () {
    $matchingClient = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $matchingProposal = Proposal::factory()->create(['client_id' => $matchingClient->id]);
    $otherProposal = Proposal::factory()->create(['client_id' => $otherClient->id]);

    $matchingActivity = Activity::factory()->create([
        'contract_id' => null,
        'proposal_id' => $matchingProposal->id,
    ]);

    Activity::factory()->create([
        'contract_id' => null,
        'proposal_id' => $otherProposal->id,
    ]);

    expect(Activity::query()->forClient($matchingClient->id)->pluck('id')->all())
        ->toBe([$matchingActivity->id]);
});

it('requires an activity to belong to exactly one source', function () {
    Activity::factory()
        ->make([
            'contract_id' => null,
            'proposal_id' => null,
        ])
        ->save();
})->throws(InvalidArgumentException::class);

it('prevents activities from belonging to both a contract and a proposal', function () {
    Activity::factory()
        ->make([
            'contract_id' => Contract::factory()->create()->id,
            'proposal_id' => Proposal::factory()->create()->id,
        ])
        ->save();
})->throws(InvalidArgumentException::class);
