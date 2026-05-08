<?php

use App\DomainAccessType;
use App\Models\Domain;
use App\Models\User;
use App\Support\RemoteServers\RemoteConnectionFactory;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds the expected ftp filesystem configuration for domains', function () {
    $domain = Domain::factory()->make([
        'access_type' => DomainAccessType::Ftp,
        'access_port' => null,
        'access_root_path' => '/public_html',
        'access_start_path' => 'site',
        'ftp_host' => '127.0.0.1',
        'ftp_user' => 'ftp-user',
        'ftp_password' => 'secret',
    ]);

    $config = app(RemoteConnectionFactory::class)->makeFilesystemConfig($domain);

    expect($config)
        ->toMatchArray([
            'driver' => 'ftp',
            'host' => '127.0.0.1',
            'username' => 'ftp-user',
            'password' => 'secret',
            'root' => '/public_html',
            'port' => 21,
        ]);
});

it('builds the expected sftp filesystem configuration for domains', function () {
    $domain = Domain::factory()->make([
        'access_type' => DomainAccessType::Sftp,
        'access_port' => 2022,
        'access_root_path' => '/home/client',
    ]);

    $config = app(RemoteConnectionFactory::class)->makeFilesystemConfig($domain);

    expect($config)
        ->toMatchArray([
            'driver' => 'sftp',
            'port' => 2022,
            'root' => '/home/client',
            'useAgent' => false,
        ]);
});

it('opens the browser from the configured start path while preserving the real root', function () {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $domain = Domain::factory()->create([
        'access_type' => DomainAccessType::Sftp,
        'access_root_path' => '/home/dh_9m4upu',
        'access_start_path' => 'contribuicao.sinregas.com.br',
    ]);

    $this->withoutVite()
        ->actingAs($user)
        ->get("/admin/domains/{$domain->getKey()}/files")
        ->assertSuccessful()
        ->assertSee('/contribuicao.sinregas.com.br');
});

it('renders remote access actions on the domain edit page', function () {
    $this->withoutVite();

    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $domain = Domain::factory()->create([
        'access_type' => DomainAccessType::Ftp,
    ]);

    $this->actingAs($user)
        ->get("/admin/domains/{$domain->getKey()}/edit")
        ->assertSuccessful()
        ->assertSee('Testar acesso')
        ->assertSee('Arquivos remotos');
});

it('shows the ssh browsing warning on the remote files page', function () {
    $this->withoutVite();

    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $domain = Domain::factory()->create([
        'access_type' => DomainAccessType::Ssh,
    ]);

    $this->actingAs($user)
        ->get("/admin/domains/{$domain->getKey()}/files")
        ->assertSuccessful()
        ->assertSee('A navegação remota foi habilitada apenas para conexões FTP e SFTP');
});
