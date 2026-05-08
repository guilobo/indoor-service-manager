<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates an admin user for deployments', function () {
    $this->artisan('app:ensure-admin-user', [
        'email' => 'contato@indoortech.com.br',
        '--password' => 'valid-password',
        '--name' => 'Indoor Tech',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'contato@indoortech.com.br')->firstOrFail();

    expect($user->name)->toBe('Indoor Tech')
        ->and($user->role)->toBe(UserRole::Admin)
        ->and(Hash::check('valid-password', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

it('updates an existing user into an admin', function () {
    User::factory()->create([
        'email' => 'contato@indoortech.com.br',
        'role' => UserRole::Operator,
        'password' => 'old-password',
    ]);

    $this->artisan('app:ensure-admin-user', [
        'email' => 'contato@indoortech.com.br',
        '--password' => 'new-password',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'contato@indoortech.com.br')->firstOrFail();

    expect($user->role)->toBe(UserRole::Admin)
        ->and(Hash::check('new-password', $user->password))->toBeTrue();
});
