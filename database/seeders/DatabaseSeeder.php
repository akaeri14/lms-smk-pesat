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
        foreach ([
            ['name' => 'Administrator SMK Pesat', 'email' => 'admin@smkpesat.id', 'role' => 'admin'],
            ['name' => 'Ahmad Fadli, S.Pd.', 'email' => 'ahmad.fadli@smkpesat.id', 'role' => 'guru'],
            ['name' => 'Siti Nurhaliza, S.Pd.', 'email' => 'siti.nurhaliza@smkpesat.id', 'role' => 'guru'],
            ['name' => 'Andi Pratama', 'email' => 'andi.pratama@smkpesat.id', 'role' => 'siswa'],
            ['name' => 'Bunga Cahyani', 'email' => 'bunga.cahyani@smkpesat.id', 'role' => 'siswa'],
            ['name' => 'Dimas Saputra', 'email' => 'dimas.saputra@smkpesat.id', 'role' => 'siswa'],
            ['name' => 'Eka Wulandari', 'email' => 'eka.wulandari@smkpesat.id', 'role' => 'siswa'],
            ['name' => 'Farhan Maulana', 'email' => 'farhan.maulana@smkpesat.id', 'role' => 'siswa'],
        ] as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [...$user, 'password' => Hash::make('password'), 'status' => 'active'],
            );
        }
    }
}
