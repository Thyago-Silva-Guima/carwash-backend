<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ServicoSeeder::class);

        $this->criarAdmin();
    }

    private function criarAdmin(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (blank($email) || blank($password)) {
            $this->command->warn('ADMIN_EMAIL/ADMIN_PASSWORD não definidos: admin não foi criado.');
            return;
        }

        if (strlen($password) < 12) {
            $this->command->error('ADMIN_PASSWORD precisa ter ao menos 12 caracteres.');
            return;
        }

        if (DB::table('usuario')->where('email', $email)->exists()) {
            return;
        }

        DB::table('usuario')->insert([
            'nome' => config('admin.name'),
            'email' => $email,
            'senha_hash' => Hash::make($password),
            'perfil' => 'admin',
        ]);
    }
}
