<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void 
    {
        // check first - jangan sampai duplikat saat jalankan seeder 
        if (User::where('email', 'admin@mindwaypos.com')->exists()) {
            $this->command->info('Admin sudah ada, skip');
            return;
        }

        User::create([
            'name'      => 'Admin',
            'email'     => 'apollondwi@wetions.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $this->command->info(' Akun admin berhasil dibuat');
        $this->command->info(' Email : apollondwi@wetions.com');
        $this->command->info(' Password : password');
        $this->command->warn(' Segera ganti password setelah login pertama');
    }
}