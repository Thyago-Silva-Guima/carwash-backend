<?php

namespace Tests\Feature;

use Tests\FeatureBase;

class AgendamentoTest extends FeatureBase
{
    private function cenario(): array
    {
        $cliente = $this->criarCliente();
        $endereco = $this->criarEndereco($cliente);
        $servico = $this->criarServico(60, 100);
        $this->criarJanela('2026-11-14', '08:00', '12:00');

        return [$cliente, $endereco, $servico];
    }

    private function dados(int $endereco, int $servico, string $dataHora = '2026-11-14T08:00:00-03:00', int $quantidade = 1): array
    {
        return [
            'endereco_id' => $endereco,
            'data_hora' => $dataHora,
            'servicos' => [['servico_id' => $servico, 'quantidade' => $quantidade]],
            'observacoes' => 'Teste',
        ];
    }

    public function test_cliente_cria_agendamento_pendente(): void
    {
        [$cliente, $endereco, $servico] = $this->cenario();

        $this->comoCliente($cliente)->postJson('/api/agendamentos', $this->dados($endereco, $servico))
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pendente')
            ->assertJsonPath('data.valor_total', '100.00');

        $this->assertDatabaseHas('agendamento', [
            'cliente_id' => $cliente,
            'status' => 'pendente',
            'data_hora' => '2026-11-14 08:00:00',
            'data_hora_fim' => '2026-11-14 09:00:00',
        ]);
        $this->assertDatabaseHas('agendamento_servico', [
            'servico_id' => $servico,
            'quantidade' => 1,
            'preco_unitario' => 100,
        ]);
    }

    public function test_quantidade_2_dobra_o_valor_e_a_duracao(): void
    {
        [$cliente, $endereco, $servico] = $this->cenario();

        $this->comoCliente($cliente)->postJson('/api/agendamentos', $this->dados($endereco, $servico, '2026-11-14T08:00:00-03:00', 2))
            ->assertStatus(201)
            ->assertJsonPath('data.valor_total', '200.00');

        $this->assertDatabaseHas('agendamento', ['data_hora_fim' => '2026-11-14 10:00:00']);
    }

    public function test_valor_enviado_pelo_front_e_ignorado(): void
    {
        [$cliente, $endereco, $servico] = $this->cenario();

        $this->comoCliente($cliente)
            ->postJson('/api/agendamentos', $this->dados($endereco, $servico) + ['valor_total' => 1])
            ->assertStatus(201)
            ->assertJsonPath('data.valor_total', '100.00');
    }

    public function test_horario_ja_ocupado_retorna_422(): void
    {
        [$cliente, $endereco, $servico] = $this->cenario();
        $this->criarAgendamento($cliente, $endereco, '2026-11-14 08:00:00', '2026-11-14 09:00:00');

        $this->comoCliente($cliente)->postJson('/api/agendamentos', $this->dados($endereco, $servico))
            ->assertStatus(422)
            ->assertJsonValidationErrors('data_hora');
    }

    public function test_horario_fora_da_janela_retorna_422(): void
    {
        [$cliente, $endereco, $servico] = $this->cenario();

        $this->comoCliente($cliente)
            ->postJson('/api/agendamentos', $this->dados($endereco, $servico, '2026-11-14T14:00:00-03:00'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('data_hora');
    }

    public function test_horario_fora_do_passo_retorna_422(): void
    {
        [$cliente, $endereco, $servico] = $this->cenario();

        $this->comoCliente($cliente)
            ->postJson('/api/agendamentos', $this->dados($endereco, $servico, '2026-11-14T08:10:00-03:00'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('data_hora');
    }

    public function test_data_no_passado_retorna_422(): void
    {
        [$cliente, $endereco, $servico] = $this->cenario();

        $this->comoCliente($cliente)
            ->postJson('/api/agendamentos', $this->dados($endereco, $servico, '2026-10-01T08:00:00-03:00'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('data_hora');
    }

    public function test_menos_de_2_horas_de_antecedencia_retorna_422(): void
    {
        [$cliente, $endereco, $servico] = $this->cenario();
        $this->criarJanela('2026-11-01', '08:00', '18:00');

        // "Agora" nos testes é 01/11 às 09:00; 10:00 está a apenas 1 hora.
        $this->comoCliente($cliente)
            ->postJson('/api/agendamentos', $this->dados($endereco, $servico, '2026-11-01T10:00:00-03:00'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('data_hora');
    }

    public function test_endereco_de_outro_cliente_retorna_422(): void
    {
        [$cliente, , $servico] = $this->cenario();
        $outro = $this->criarCliente();
        $enderecoDoOutro = $this->criarEndereco($outro);

        $this->comoCliente($cliente)->postJson('/api/agendamentos', $this->dados($enderecoDoOutro, $servico))
            ->assertStatus(422)
            ->assertJsonValidationErrors('endereco_id');
    }

    public function test_servico_inativo_retorna_422(): void
    {
        [$cliente, $endereco] = $this->cenario();
        $inativo = $this->criarServico(60, 100, false);

        $this->comoCliente($cliente)->postJson('/api/agendamentos', $this->dados($endereco, $inativo))
            ->assertStatus(422)
            ->assertJsonValidationErrors('servicos.0.servico_id');
    }

    public function test_sem_login_retorna_401(): void
    {
        $this->postJson('/api/agendamentos', [])->assertStatus(401);
    }

    public function test_cliente_ve_so_os_proprios_agendamentos(): void
    {
        [$cliente, $endereco] = $this->cenario();
        $outro = $this->criarCliente();
        $enderecoDoOutro = $this->criarEndereco($outro);

        $meu = $this->criarAgendamento($cliente, $endereco, '2026-11-14 08:00:00', '2026-11-14 09:00:00');
        $dele = $this->criarAgendamento($outro, $enderecoDoOutro, '2026-11-14 10:00:00', '2026-11-14 11:00:00');

        $this->comoCliente($cliente)->getJson('/api/agendamentos')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $meu);

        $this->comoCliente($cliente)->getJson("/api/agendamentos/{$meu}")->assertStatus(200);
        $this->comoCliente($cliente)->getJson("/api/agendamentos/{$dele}")->assertStatus(404);
    }
}
