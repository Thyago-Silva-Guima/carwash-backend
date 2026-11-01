<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServicoSeeder extends Seeder
{
    private const PRECO_TEMPORARIO = 0.00;
    private const DURACAO_TEMPORARIA_MIN = 60;

    public function run(): void
    {
        $servicos = [
            ['Lavagem de Muros e Paredes Externas',      'muro'],
            ['Lavagem de Portões e Grades',              'portao'],
            ['Lavagem de Fachadas e Revestimentos',      'fachada'],
            ['Lavagem de Calçadas e Pisos Externos',     'calcada'],
            ['Lavagem de Pedras e Revestimentos',        'pedra'],
            ['Lavagem de Garagens e Áreas de Circulação', 'garagem'],
            ['Lavagem de Áreas de Lazer',                'area_lazer'],
            ['Lavagem de Áreas PET e Espaços Externos',  'area_pet'],
        ];

        foreach ($servicos as [$nome, $categoria]) {
            if (DB::table('servico')->where('nome', $nome)->exists()) {
                continue;
            }

            DB::table('servico')->insert([
                'nome' => $nome,
                'descricao' => null,
                'categoria' => $categoria,
                'duracao_minutos' => self::DURACAO_TEMPORARIA_MIN,
                'preco_base' => self::PRECO_TEMPORARIO,
                'ativo' => 1,
            ]);
        }
    }
}
