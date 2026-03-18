<?php

namespace App;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DomainStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Inactive => 'Inativo',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
        };
    }
}
