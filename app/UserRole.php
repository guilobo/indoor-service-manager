<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Operator = 'operator';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Operator => 'Operador',
        };
    }
}
