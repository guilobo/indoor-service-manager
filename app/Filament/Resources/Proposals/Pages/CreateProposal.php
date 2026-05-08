<?php

namespace App\Filament\Resources\Proposals\Pages;

use App\Filament\Resources\Proposals\ProposalResource;
use App\Models\Proposal;
use Filament\Resources\Pages\CreateRecord;

class CreateProposal extends CreateRecord
{
    protected static string $resource = ProposalResource::class;

    protected function getRedirectUrl(): string
    {
        return ProposalResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['attachments'] = Proposal::prepareAttachmentItemsForStorage($data['attachments'] ?? [], 'proposals/attachments');

        return $data;
    }
}
