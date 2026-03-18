<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Models\Activity;
use App\Models\Contract;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Atividade')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('contract_id')
                                    ->label('Contrato')
                                    ->options(Contract::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (string $operation, callable $get): bool => $operation === 'edit' || filled($get('locked_contract_id')))
                                    ->dehydrated()
                                    ->required(),
                                Hidden::make('locked_contract_id')
                                    ->dehydrated(false),
                                Select::make('service_id')
                                    ->label('Servico')
                                    ->options(Service::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Nome')
                                            ->required()
                                            ->maxLength(255),
                                        Textarea::make('description')
                                            ->label('Descricao')
                                            ->rows(4),
                                    ])
                                    ->createOptionUsing(fn (array $data): int => Service::query()->create($data)->getKey()),
                                TextInput::make('title')
                                    ->label('Titulo')
                                    ->required()
                                    ->maxLength(255),
                                DatePicker::make('activity_date')
                                    ->label('Data da atividade')
                                    ->required(),
                                TextInput::make('reference_period')
                                    ->label('Periodo de referencia')
                                    ->helperText('Exemplo: 2026-03')
                                    ->maxLength(20),
                                Placeholder::make('duration_preview')
                                    ->label('Duracao calculada')
                                    ->content(fn (callable $get): string => self::formatMinutes(Activity::calculateDurationMinutes($get('time_entries') ?? []))),
                            ]),
                        RichEditor::make('description')
                            ->label('Descricao detalhada')
                            ->columnSpanFull(),
                    ]),
                Section::make('Controle de tempo')
                    ->description('Cada clique alterna entre iniciar e encerrar o ultimo intervalo aberto.')
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->headerActions([
                        Action::make('toggle_time_entry')
                            ->label(fn (callable $get): string => collect($get('time_entries') ?? [])
                                ->contains(fn (mixed $entry): bool => is_array($entry) && filled($entry['started_at'] ?? null) && blank($entry['ended_at'] ?? null))
                                ? 'Finalizar servico'
                                : 'Iniciar servico')
                            ->color('gray')
                            ->icon('heroicon-o-play-pause')
                            ->visible(fn ($livewire): bool => method_exists($livewire, 'toggleTimeEntry'))
                            ->action('toggleTimeEntry'),
                    ])
                    ->schema([
                        Repeater::make('time_entries')
                            ->label('Intervalos de tempo')
                            ->default([])
                            ->deletable()
                            ->addable(false)
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): string => filled($state['ended_at'] ?? null) ? 'Intervalo concluido' : 'Em andamento')
                            ->schema([
                                DateTimePicker::make('started_at')
                                    ->label('Inicio')
                                    ->seconds(false)
                                    ->required(),
                                DateTimePicker::make('ended_at')
                                    ->label('Fim')
                                    ->seconds(false),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('Anexos e links')
                    ->schema([
                        FileUpload::make('images')
                            ->label('Imagens')
                            ->directory('activities/images')
                            ->image()
                            ->multiple()
                            ->disk('public'),
                        FileUpload::make('files')
                            ->label('Arquivos')
                            ->directory('activities/files')
                            ->multiple()
                            ->disk('public'),
                        Repeater::make('external_links')
                            ->label('Links externos')
                            ->schema([
                                TextInput::make('url')
                                    ->label('URL')
                                    ->url()
                                    ->required(),
                            ])
                            ->default([])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return Str::padLeft((string) $hours, 2, '0').'h '.Str::padLeft((string) $remainingMinutes, 2, '0').'min';
    }
}
