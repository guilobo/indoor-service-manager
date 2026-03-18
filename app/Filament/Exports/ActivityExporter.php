<?php

namespace App\Filament\Exports;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Contract;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class ActivityExporter extends Exporter
{
    protected static ?string $model = Activity::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('activity_date')
                ->label('Data')
                ->formatStateUsing(fn ($state): string => blank($state) ? '-' : now()->parse($state)->format('d/m/Y')),
            ExportColumn::make('contract.client.name')
                ->label('Cliente'),
            ExportColumn::make('contract.name')
                ->label('Contrato'),
            ExportColumn::make('service.name')
                ->label('Servico')
                ->default('-'),
            ExportColumn::make('title')
                ->label('Atividade'),
            ExportColumn::make('description')
                ->label('Descricao')
                ->state(fn (Activity $record): string => self::formatDescription($record->description)),
            ExportColumn::make('duration_minutes')
                ->label('Duracao')
                ->state(fn (Activity $record): string => self::formatMinutes($record->duration_minutes)),
            ExportColumn::make('time_entries_summary')
                ->label('Intervalos')
                ->state(fn (Activity $record): string => self::formatTimeEntries($record)),
            ExportColumn::make('images_list')
                ->label('Imagens')
                ->state(fn (Activity $record): string => self::formatMediaList($record->images_list)),
            ExportColumn::make('files_list')
                ->label('Arquivos')
                ->state(fn (Activity $record): string => self::formatMediaList($record->files_list)),
            ExportColumn::make('external_links_list')
                ->label('Links externos')
                ->state(fn (Activity $record): string => blank($record->external_links_list) ? '-' : implode(' | ', $record->external_links_list)),
            ExportColumn::make('reference_period')
                ->label('Periodo de referencia')
                ->enabledByDefault(false),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'O relatorio de atividades foi gerado com '.Number::format($export->successful_rows).' '.str('linha')->plural($export->successful_rows).'.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam na exportacao.';
        }

        $body .= ' Clique na notificacao para baixar o arquivo.';

        return $body;
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['contract.client', 'service']);
    }

    public function getFileName(Export $export): string
    {
        $options = $this->getOptions();

        $clientName = filled($options['client_id'] ?? null)
            ? Client::query()->whereKey($options['client_id'])->value('name')
            : 'todos-clientes';

        $contractName = filled($options['contract_id'] ?? null)
            ? Contract::query()->whereKey($options['contract_id'])->value('name')
            : 'todos-contratos';

        $startDate = filled($options['start_date'] ?? null) ? now()->parse($options['start_date'])->format('Y-m-d') : 'inicio';
        $endDate = filled($options['end_date'] ?? null) ? now()->parse($options['end_date'])->format('Y-m-d') : 'fim';

        return Str::of("relatorio-{$clientName}-{$contractName}-{$startDate}-{$endDate}")
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->append("-{$export->getKey()}")
            ->toString();
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    protected static function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return str_pad((string) $hours, 2, '0', STR_PAD_LEFT).'h '.str_pad((string) $remainingMinutes, 2, '0', STR_PAD_LEFT).'min';
    }

    protected static function formatTimeEntries(Activity $record): string
    {
        $timeEntries = Activity::sortTimeEntriesDescending($record->time_entries ?? []);

        if (blank($timeEntries)) {
            return '-';
        }

        return collect($timeEntries)
            ->map(function (array $entry): string {
                $startedAt = now()->parse($entry['started_at'])->format('d/m/Y H:i');
                $endedAt = filled($entry['ended_at'] ?? null)
                    ? now()->parse($entry['ended_at'])->format('H:i')
                    : 'em andamento';

                return "{$startedAt} - {$endedAt}";
            })
            ->implode(' | ');
    }

    protected static function formatDescription(mixed $description): string
    {
        if (blank($description)) {
            return '-';
        }

        if (is_array($description)) {
            return self::extractRichTextContent($description);
        }

        if (! is_string($description)) {
            return '-';
        }

        $decodedDescription = json_decode($description, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedDescription)) {
            return self::extractRichTextContent($decodedDescription);
        }

        return Str::of(strip_tags($description))
            ->replace(["\r\n", "\r", "\n"], ' ')
            ->squish()
            ->value() ?: '-';
    }

    /**
     * @param  array<mixed>  $content
     */
    protected static function extractRichTextContent(array $content): string
    {
        $text = collect(self::flattenRichTextNodes($content))
            ->filter(fn (mixed $value): bool => filled($value))
            ->implode(' ');

        return Str::of($text)
            ->replace(["\r\n", "\r", "\n"], ' ')
            ->squish()
            ->value() ?: '-';
    }

    /**
     * @param  array<mixed>  $node
     * @return list<string>
     */
    protected static function flattenRichTextNodes(array $node): array
    {
        $segments = [];

        if (filled($node['text'] ?? null) && is_string($node['text'])) {
            $segments[] = $node['text'];
        }

        foreach ($node['content'] ?? [] as $childNode) {
            if (is_array($childNode)) {
                $segments = [...$segments, ...self::flattenRichTextNodes($childNode)];
            }
        }

        return $segments;
    }

    /**
     * @param  array<int, array{title: string, path: string, url: string}>|null  $items
     */
    protected static function formatMediaList(?array $items): string
    {
        if (blank($items)) {
            return '-';
        }

        return collect($items)
            ->map(fn (array $item): string => "{$item['title']}: {$item['url']}")
            ->implode(' | ') ?: '-';
    }
}
