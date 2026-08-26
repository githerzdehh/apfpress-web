<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ContentSeeder::class,
            CatalogSeeder::class,
        ]);

        $email = config('apf.owner_email');
        $password = config('apf.owner_password');

        if (! app()->isProduction()) {
            $email ??= 'owner@apfpress.test';
            $password ??= 'ChangeMe!12345';
        }

        if ($email && $password) {
            User::query()->updateOrCreate(['email' => $email], [
                'name' => 'APF Press Owner',
                'password' => Hash::make($password),
                'role' => 'owner',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
        } else {
            $this->command?->warn('No production owner was created. Set APF_OWNER_EMAIL and APF_OWNER_PASSWORD before seeding.');
        }
    }
}
