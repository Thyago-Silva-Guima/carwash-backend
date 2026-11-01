<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'telefone' => fake()->numerify('419########'),
            'email' => fake()->unique()->safeEmail(),
            'senha_hash' => static::$password ??= Hash::make('password'),
            'aceite_termos_em' => now(),
        ];
    }
}
