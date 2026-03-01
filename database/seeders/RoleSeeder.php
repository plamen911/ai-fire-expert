<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('user', 'web');

        $admin = User::where('email', 'plamen326@gmail.com')->first();

        if ($admin) {
            $admin->assignRole('admin');
        }

        User::whereDoesntHave('roles')->each(function (User $user): void {
            $user->assignRole('user');
        });
    }
}
