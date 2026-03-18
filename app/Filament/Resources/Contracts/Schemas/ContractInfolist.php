<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Models\Contract;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumo do contrato')
                    ->schema([
                        TextEntry::make('client.name')
                            ->label('Cliente'),
                        TextEntry::make('name')
                            ->label('Contrato'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('start_date')
                            ->label('Início')
                            ->date('d/m/Y'),
                        TextEntry::make('end_date')
                            ->label('Fim')
                            ->placeholder('Indeterminado')
                            ->date('d/m/Y'),
                        TextEntry::make('monthly_hours')
                            ->label('Horas mensais')
                            ->numeric(decimalPlaces: 2),
                        TextEntry::make('hourly_rate')
                            ->label('Valor hora')
                            ->money('BRL', locale: 'pt_BR'),
                        TextEntry::make('domain_rate')
                            ->label('Valor domínio')
                            ->money('BRL', locale: 'pt_BR'),
                        TextEntry::make('total_domains')
                            ->label('Total de domínios')
                            ->state(fn (Contract $record): int => $record->total_domains),
                        TextEntry::make('hours_cost')
                            ->label('Custo de horas')
                            ->state(fn (Contract $record): string => 'R$ '.number_format($record->hours_cost, 2, ',', '.')),
                        TextEntry::make('domain_cost')
                            ->label('Custo de domínios')
                            ->state(fn (Contract $record): string => 'R$ '.number_format($record->domain_cost, 2, ',', '.')),
                        TextEntry::make('estimated_monthly_value')
                            ->label('Valor mensal estimado')
                            ->state(fn (Contract $record): string => 'R$ '.number_format($record->estimated_monthly_value, 2, ',', '.')),
                        TextEntry::make('used_hours')
                            ->label('Horas usadas no mês atual')
                            ->state(fn (Contract $record): string => number_format($record->usageSummary(now()->startOfMonth(), now()->endOfMonth())['total_used_hours'], 2, ',', '.').' h'),
                        TextEntry::make('remaining_hours')
                            ->label('Saldo no mês atual')
                            ->state(fn (Contract $record): string => number_format($record->usageSummary(now()->startOfMonth(), now()->endOfMonth())['remaining_hours'], 2, ',', '.').' h'),
                        TextEntry::make('description')
                            ->label('Descrição')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('notes')
                            ->label('Observações')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
