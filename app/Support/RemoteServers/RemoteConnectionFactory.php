<?php

namespace App\Support\RemoteServers;

use App\DomainAccessType;
use App\Models\Domain;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemOperator;
use phpseclib3\Net\SSH2;
use RuntimeException;

class RemoteConnectionFactory
{
    public function testConnection(Domain $domain): string
    {
        return match ($domain->access_type) {
            DomainAccessType::Ftp, DomainAccessType::Sftp => $this->testFilesystemConnection($domain),
            DomainAccessType::Ssh => $this->testSshConnection($domain),
            default => throw new RuntimeException('Tipo de acesso remoto não suportado.'),
        };
    }

    public function makeFilesystemAdapter(Domain $domain): FilesystemAdapter
    {
        if (! $domain->canBrowseFiles()) {
            throw new RuntimeException('A navegação de arquivos remotos está disponível apenas para FTP e SFTP.');
        }

        return Storage::build($this->makeFilesystemConfig($domain));
    }

    public function makeFilesystemOperator(Domain $domain): FilesystemOperator
    {
        return $this->makeFilesystemAdapter($domain)->getDriver();
    }

    /**
     * @return array<string, mixed>
     */
    public function makeFilesystemConfig(Domain $domain): array
    {
        $accessType = $domain->access_type instanceof DomainAccessType
            ? $domain->access_type
            : DomainAccessType::from((string) $domain->access_type);

        $config = [
            'driver' => $accessType->value,
            'host' => (string) $domain->ftp_host,
            'username' => (string) $domain->ftp_user,
            'password' => (string) $domain->ftp_password,
            'root' => $domain->access_root_path ?: '/',
            'port' => $domain->access_port ?: $this->defaultPortFor($accessType),
            'timeout' => 30,
        ];

        if ($accessType === DomainAccessType::Ftp) {
            return [
                ...$config,
                'passive' => true,
                'ssl' => false,
                'ignorePassiveAddress' => null,
            ];
        }

        return [
            ...$config,
            'useAgent' => false,
        ];
    }

    public function defaultPortFor(DomainAccessType $accessType): int
    {
        return match ($accessType) {
            DomainAccessType::Ftp => 21,
            DomainAccessType::Sftp, DomainAccessType::Ssh => 22,
        };
    }

    protected function testFilesystemConnection(Domain $domain): string
    {
        $files = $this->makeFilesystemAdapter($domain)->getDriver()->listContents('', false);

        foreach ($files as $file) {
            break;
        }

        return sprintf(
            'Conexão %s validada com sucesso em %s:%s.',
            strtoupper($domain->access_type->value),
            $domain->ftp_host,
            $domain->access_port ?: $this->defaultPortFor($domain->access_type),
        );
    }

    protected function testSshConnection(Domain $domain): string
    {
        $ssh = new SSH2((string) $domain->ftp_host, $domain->access_port ?: $this->defaultPortFor(DomainAccessType::Ssh));

        if (! $ssh->login((string) $domain->ftp_user, (string) $domain->ftp_password)) {
            throw new RuntimeException('Não foi possível autenticar via SSH com as credenciais informadas.');
        }

        $currentDirectory = trim((string) $ssh->exec('pwd'));

        return $currentDirectory !== ''
            ? "Conexão SSH validada com sucesso. Diretório atual: {$currentDirectory}."
            : 'Conexão SSH validada com sucesso.';
    }
}
