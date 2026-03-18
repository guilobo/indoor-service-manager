<?php

namespace App\Filament\Resources\Domains\Tables;

use App\DomainStatus;
use App\Filament\Resources\Domains\DomainResource;
use App\Models\Client;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DomainsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => DomainResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable(),
                TextColumn::make('contract.name')
                    ->label('Contrato')
                    ->searchable(),
                TextColumn::make('domain_name')
                    ->label('Domínio')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('hosting')
                    ->label('Hospedagem')
                    ->searchable(),
                TextColumn::make('ftp_host')
                    ->label('Host FTP')
                    ->searchable(),
                TextColumn::make('ftp_user')
                    ->label('Usuário FTP')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('cliente')
                    ->label('Cliente')
                    ->schema([
                        Select::make('client_id')
                            ->label('Cliente')
                            ->options(Client::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['client_id'] ?? null, fn (Builder $builder, $clientId): Builder => $builder->where('client_id', $clientId))),
                Filter::make('status')
                    ->label('Status')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(collect(DomainStatus::cases())->mapWithKeys(fn (DomainStatus $status): array => [$status->value => $status->getLabel()])->all()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['status'] ?? null, fn (Builder $builder, $status): Builder => $builder->where('status', $status))),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('domain_name');
    }
}
