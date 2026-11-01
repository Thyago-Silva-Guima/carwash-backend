<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\FeatureBase;

class JanelasTest extends FeatureBase
{
    private function umaJanela(array $sobrescrever = []): array
    {
        return $sobrescrever + [
            'data' => '2026-11-14',
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'capacidade' => 1,
        ];
    }

    public function test_admin_cria_janela(): void
    {
        $admin = $this->criarUsuario('admin');

        $this->comoUsuario($admin)->postJson('/api/admin/disponibilidades', $this->umaJanela())
            ->assertStatus(201)
            ->assertJsonPath('data.data', '2026-11-14')
            ->assertJsonPath('data.hora_inicio', '08:00:00');

        $this->assertDatabaseHas('disponibilidade', [
            'data' => '2026-11-14',
            'hora_inicio' => '08:00:00',
            'usuario_id' => $admin,
        ]);
    }

    public function test_sem_login_retorna_401(): void
    {
        $this->postJson('/api/admin/disponibilidades', $this->umaJanela())->assertStatus(401);
    }

    public function test_cliente_nao_acessa_rota_admin(): void
    {
        $cliente = $this->criarCliente();

        $this->comoCliente($cliente)->postJson('/api/admin/disponibilidades', $this->umaJanela())
            ->assertStatus(403);
    }

    public function test_funcionario_nao_pode_criar_janela(): void
    {
        $funcionario = $this->criarUsuario('funcionario');

        $this->comoUsuario($funcionario)->postJson('/api/admin/disponibilidades', $this->umaJanela())
            ->assertStatus(403);
    }

    public function test_janela_sobreposta_retorna_422(): void
    {
        $admin = $this->criarUsuario();
        $this->criarJanela('2026-11-14', '08:00', '12:00');

        $this->comoUsuario($admin)
            ->postJson('/api/admin/disponibilidades', $this->umaJanela(['hora_inicio' => '11:00', 'hora_fim' => '14:00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hora_inicio');
    }

    public function test_hora_fim_antes_do_inicio_retorna_422(): void
    {
        $admin = $this->criarUsuario();

        $this->comoUsuario($admin)
            ->postJson('/api/admin/disponibilidades', $this->umaJanela(['hora_inicio' => '12:00', 'hora_fim' => '08:00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hora_fim');
    }

    public function test_data_no_passado_retorna_422(): void
    {
        $admin = $this->criarUsuario();

        $this->comoUsuario($admin)
            ->postJson('/api/admin/disponibilidades', $this->umaJanela(['data' => '2026-10-01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('data');
    }

    public function test_lote_cria_janelas_e_ignora_as_que_conflitam(): void
    {
        $admin = $this->criarUsuario();
        $this->criarJanela('2026-11-14', '08:00', '12:00'); // já existe num dos sábados

        // Sábados de novembro/2026: 7, 14, 21 e 28.
        $this->comoUsuario($admin)->postJson('/api/admin/disponibilidades/lote', [
            'data_inicio' => '2026-11-01',
            'data_fim' => '2026-11-30',
            'dias_semana' => [6],
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'capacidade' => 1,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.criadas', 3)
            ->assertJsonCount(1, 'data.ignoradas');

        $this->assertSame(4, DB::table('disponibilidade')->count());
    }

    public function test_alterar_janela_sem_agendamentos(): void
    {
        $admin = $this->criarUsuario();
        $janela = $this->criarJanela('2026-11-14', '08:00', '12:00');

        $this->comoUsuario($admin)->putJson("/api/admin/disponibilidades/{$janela}", ['hora_fim' => '13:00'])
            ->assertStatus(200)
            ->assertJsonPath('data.hora_fim', '13:00:00');
    }

    public function test_encurtar_janela_deixando_agendamento_de_fora_retorna_409(): void
    {
        $admin = $this->criarUsuario();
        $cliente = $this->criarCliente();
        $endereco = $this->criarEndereco($cliente);
        $janela = $this->criarJanela('2026-11-14', '08:00', '12:00');
        $agendamento = $this->criarAgendamento($cliente, $endereco, '2026-11-14 11:00:00', '2026-11-14 12:00:00');

        $this->comoUsuario($admin)->putJson("/api/admin/disponibilidades/{$janela}", ['hora_fim' => '11:00'])
            ->assertStatus(409)
            ->assertJsonPath('agendamentos.0', $agendamento);
    }

    public function test_apagar_janela_sem_agendamentos_retorna_204(): void
    {
        $admin = $this->criarUsuario();
        $janela = $this->criarJanela('2026-11-14', '08:00', '12:00');

        $this->comoUsuario($admin)->deleteJson("/api/admin/disponibilidades/{$janela}")->assertStatus(204);

        $this->assertDatabaseMissing('disponibilidade', ['id' => $janela]);
    }

    public function test_apagar_janela_com_agendamento_retorna_409(): void
    {
        $admin = $this->criarUsuario();
        $cliente = $this->criarCliente();
        $endereco = $this->criarEndereco($cliente);
        $janela = $this->criarJanela('2026-11-14', '08:00', '12:00');
        $agendamento = $this->criarAgendamento($cliente, $endereco, '2026-11-14 08:00:00', '2026-11-14 09:00:00');

        $this->comoUsuario($admin)->deleteJson("/api/admin/disponibilidades/{$janela}")
            ->assertStatus(409)
            ->assertJsonPath('agendamentos.0', $agendamento);

        $this->assertDatabaseHas('disponibilidade', ['id' => $janela]);
    }
}
