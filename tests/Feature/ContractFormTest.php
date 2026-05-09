<?php

use App\Filament\Resources\Contracts\Schemas\ContractForm;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('renders a direct CDN link for an uploaded contract file', function (): void {
    config()->set('filesystems.disks.public', [
        'driver' => 'gel5',
        'endpoint' => 'https://files.test/api/index.php',
        'key' => 'test-key',
        'root' => 'itservice',
        'url' => 'https://files.gel5.com/cdn',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
    ]);

    Storage::forgetDisk('public');

    $html = ContractForm::contractFileLink('contracts/files/contract.pdf')->toHtml();

    expect($html)
        ->toContain('href="https://files.gel5.com/cdn/itservice/contracts/files/contract.pdf"')
        ->toContain('target="_blank"')
        ->toContain('Abrir contract.pdf');
});

it('builds the contract form schema without missing component classes', function (): void {
    expect(ContractForm::configure(Schema::make())->getComponents())->not->toBeEmpty();
});
