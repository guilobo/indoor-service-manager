<?php

namespace App;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContractStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Inactive => 'Inativo',
            self::Expired => 'Expirado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Expired => 'danger',
        };
    }
}
