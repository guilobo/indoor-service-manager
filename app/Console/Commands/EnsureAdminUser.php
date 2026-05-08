<?php

namespace App\Console\Commands;

use App\Models\User;
use App\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class EnsureAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ensure-admin-user
        {email : Admin e-mail address}
        {--password= : Password to set for the admin user}
        {--name=Administrador : Admin display name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update an administrator user for deployments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $password = $this->option('password');
        $name = (string) $this->option('name');

        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
            'name' => $name,
        ], [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', Password::min(8)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'role' => UserRole::Admin,
            'password' => $password,
            'email_verified_at' => now(),
        ])->save();

        $this->info("Admin user [{$email}] is ready.");

        return self::SUCCESS;
    }
}
