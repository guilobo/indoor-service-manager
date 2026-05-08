<?php

namespace App\Filament\Resources\Domains\Pages;

use App\Filament\Resources\Domains\DomainResource;
use App\Support\RemoteServers\RemoteConnectionFactory;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditDomain extends EditRecord
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testAccess')
                ->label('Testar acesso')
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->action(function (): void {
                    try {
                        $message = app(RemoteConnectionFactory::class)->testConnection($this->getRecord());

                        Notification::make()
                            ->success()
                            ->title('Acesso validado')
                            ->body($message)
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Falha ao validar acesso')
                            ->body($exception->getMessage())
                            ->send();
                    }
                }),
            Action::make('browseFiles')
                ->label('Arquivos remotos')
                ->icon('heroicon-o-folder-open')
                ->url(fn (): string => DomainResource::getUrl('files', ['record' => $this->getRecord()])),
            DeleteAction::make(),
        ];
    }
}
