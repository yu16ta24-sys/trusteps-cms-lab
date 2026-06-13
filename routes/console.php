<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('app:create-admin {email} {password} {--name=Admin}', function (string $email, string $password) {
    $name = (string) $this->option('name');

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Invalid email address.');
        return self::FAILURE;
    }

    if (mb_strlen($password) < 8) {
        $this->error('Password must be at least 8 characters.');
        return self::FAILURE;
    }

    $user = User::updateOrCreate(
        ['email' => $email],
        [
            'name' => $name ?: 'Admin',
            'password' => Hash::make($password),
        ]
    );

    $this->info('Admin user is ready: '.$user->email);

    return self::SUCCESS;
})->purpose('Create or update the first admin user for the research MVP');
