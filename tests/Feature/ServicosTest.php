<?php

use App\Models\Servico;

function novoServico(array $sobrescrever = []): Servico
{
    return Servico::create(array_merge([
        'nome' => 'Lavagem de Muros',
        'categoria' => 'muro',
        'duracao_minutos' => 60,
        'preco_base' => 0,
    ], $sobrescrever));
}

it('lista só os serviços ativos, sem precisar de login', function () {
    novoServico();
    novoServico(['nome' => 'Serviço desativado', 'ativo' => false]);

    $this->getJson('/api/servicos')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonStructure([['id', 'nome', 'descricao', 'categoria', 'duracao_minutos', 'preco_base']])
        ->assertJsonFragment([
            'nome' => 'Lavagem de Muros',
            'categoria' => 'muro',
            'duracao_minutos' => 60,
            'preco_base' => '0.00',
        ])
        ->assertJsonMissingPath('0.ativo')
        ->assertJsonMissing(['nome' => 'Serviço desativado']);
});

it('devolve lista vazia quando não há serviços ativos', function () {
    $this->getJson('/api/servicos')
        ->assertOk()
        ->assertExactJson([]);
});
