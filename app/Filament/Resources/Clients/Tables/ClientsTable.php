<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => ClientResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('company_name')
                    ->label('Empresa')
                    ->searchable(),
                TextColumn::make('document')
                    ->label('CPF/CNPJ')
                    ->searchable(),
                TextColumn::make('contracts_count')
                    ->label('Contratos')
                    ->badge()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefone')
                    ->searchable(),
                TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Cadastro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('busca_rapida')
                    ->label('Busca rápida')
                    ->schema([
                        TextInput::make('term')
                            ->label('Nome, empresa ou documento'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $term = $data['term'] ?? null;

                        if (blank($term)) {
                            return $query;
                        }

                        return $query->where(function (Builder $clientQuery) use ($term): void {
                            $clientQuery
                                ->where('name', 'like', "%{$term}%")
                                ->orWhere('company_name', 'like', "%{$term}%")
                                ->orWhere('document', 'like', "%{$term}%");
                        });
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
