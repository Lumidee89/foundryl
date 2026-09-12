<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperadmin extends Command
{
    protected $signature = 'foundryl:superadmin {email?} {--name=}';

    protected $description = 'Create a dedicated Foundryl platform administrator with a privately entered password';

    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->argument('email') ?: $this->ask('Superadmin email'))));
        $name = $this->option('name') ?: $this->ask('Superadmin name');
        if (User::where('email', $email)->exists()) {
            $this->error('That email already exists. Use a separate platform administrator email.');

            return self::FAILURE;
        }
        $password = $this->secret('Password (at least 6 characters)');
        $confirmation = $this->secret('Confirm password');
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], ['name' => 'required|string|max:120', 'email' => 'required|email|max:254', 'password' => ['required', 'confirmed', Password::min(6)]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($name, $email, $password) {
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->is_superadmin = true;
            $user->save();
            DB::table('platform_audit_logs')->insert(['actor_user_id' => $user->id, 'action' => 'superadmin.created', 'created_at' => now()]);
        });
        $this->info('Superadmin created. Log in at /login, then set up an authenticator app. All company workspaces remain free.');

        return self::SUCCESS;
    }
}
