<?php

namespace App\Filament\Resources\Proposals\Pages;

use App\Filament\Resources\Proposals\ProposalResource;
use App\Models\Proposal;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProposal extends EditRecord
{
    protected static string $resource = ProposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['attachments'] = Proposal::prepareAttachmentItemsForStorage($data['attachments'] ?? [], 'proposals/attachments', false);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['attachments'] = Proposal::prepareAttachmentItemsForStorage($data['attachments'] ?? [], 'proposals/attachments');

        return $data;
    }
}
