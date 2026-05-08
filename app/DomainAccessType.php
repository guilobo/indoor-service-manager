<?php

namespace App;

use Filament\Support\Contracts\HasLabel;

enum DomainAccessType: string implements HasLabel
{
    case Ftp = 'ftp';
    case Sftp = 'sftp';
    case Ssh = 'ssh';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Ftp => 'FTP',
            self::Sftp => 'SFTP',
            self::Ssh => 'SSH',
        };
    }
}
