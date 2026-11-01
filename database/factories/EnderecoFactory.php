<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Endereco;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Endereco>
 */
class EnderecoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'rua' => fake()->streetName(),
            'numero' => (string) fake()->numberBetween(1, 9999),
            'bairro' => fake()->word(),
            'complemento' => null,
            'cidade' => 'Curitiba',
            'cep' => fake()->numerify('########'),
        ];
    }
}
