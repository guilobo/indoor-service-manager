<?php

namespace App;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProposalBillingType: string implements HasColor, HasLabel
{
    case Hourly = 'hourly';
    case Fixed = 'fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hourly => 'Por hora',
            self::Fixed => 'Valor definido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Hourly => 'info',
            self::Fixed => 'success',
        };
    }
}
