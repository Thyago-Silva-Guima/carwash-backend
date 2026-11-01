<?php

namespace Tests\Feature;

use Tests\FeatureBase;

class DisponibilidadeTest extends FeatureBase
{
    private function cenario(): array
    {
        $cliente = $this->criarCliente();
        $endereco = $this->criarEndereco($cliente);
        $servico = $this->criarServico(60, 100);
        $this->criarJanela('2026-11-14', '08:00', '12:00');

        return [$cliente, $endereco, $servico];
    }

    public function test_horarios_livres_do_dia(): void
    {
        [$cliente, , $servico] = $this->cenario();

        $this->comoCliente($cliente)->getJson("/api/disponibilidade?data=2026-11-14&servico_id={$servico}")
            ->assertStatus(200)
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.0.inicio', '2026-11-14T08:00:00-03:00')
            ->assertJsonPath('data.0.fim', '2026-11-14T09:00:00-03:00');
    }

    public function test_agendamento_existente_bloqueia_os_horarios_que_colidem(): void
    {
        [$cliente, $endereco, $servico] = $this->cenario();
        $this->criarAgendamento($cliente, $endereco, '2026-11-14 08:00:00', '2026-11-14 09:00:00');

        // 08:00 e 08:30 colidem; sobram 09:00 a 11:00.
        $this->comoCliente($cliente)->getJson("/api/disponibilidade?data=2026-11-14&servico_id={$servico}")
            ->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.inicio', '2026-11-14T09:00:00-03:00');
    }

    public function test_agendamento_cancelado_nao_bloqueia_horario(): void
    {
        [$cliente, $endereco, $servico] = $this->cenario();
        $this->criarAgendamento($cliente, $endereco, '2026-11-14 08:00:00', '2026-11-14 09:00:00', 'cancelado');

        $this->comoCliente($cliente)->getJson("/api/disponibilidade?data=2026-11-14&servico_id={$servico}")
            ->assertJsonCount(7, 'data');
    }

    public function test_horarios_do_proprio_dia_respeitam_a_antecedencia_minima(): void
    {
        $cliente = $this->criarCliente();
        $servico = $this->criarServico(60);
        // "Agora" nos testes é 01/11 às 09:00; com 2 h de antecedência, o primeiro é 11:00.
        $this->criarJanela('2026-11-01', '08:00', '12:00');

        $this->comoCliente($cliente)->getJson("/api/disponibilidade?data=2026-11-01&servico_id={$servico}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.inicio', '2026-11-01T11:00:00-03:00');
    }

    public function test_dia_sem_janela_retorna_lista_vazia(): void
    {
        $cliente = $this->criarCliente();
        $servico = $this->criarServico(60);

        $this->comoCliente($cliente)->getJson("/api/disponibilidade?data=2026-11-20&servico_id={$servico}")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_dias_com_vaga_no_mes(): void
    {
        [$cliente, , $servico] = $this->cenario();

        $this->comoCliente($cliente)->getJson("/api/disponibilidade/dias?mes=2026-11&servico_id={$servico}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.data', '2026-11-14')
            ->assertJsonPath('data.0.livres', 7);
    }

    public function test_dia_totalmente_ocupado_nao_aparece_nos_dias_com_vaga(): void
    {
        [$cliente, $endereco, $servico] = $this->cenario();
        $this->criarAgendamento($cliente, $endereco, '2026-11-14 08:00:00', '2026-11-14 12:00:00');

        $this->comoCliente($cliente)->getJson("/api/disponibilidade/dias?mes=2026-11&servico_id={$servico}")
            ->assertJsonCount(0, 'data');
    }

    public function test_sem_login_retorna_401(): void
    {
        $this->getJson('/api/disponibilidade?data=2026-11-14&servico_id=1')->assertStatus(401);
    }

    public function test_servico_inexistente_retorna_422(): void
    {
        $cliente = $this->criarCliente();

        $this->comoCliente($cliente)->getJson('/api/disponibilidade?data=2026-11-14&servico_id=999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('servico_id');
    }
}
