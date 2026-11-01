<?php

namespace Tests\Feature;

use App\Events\AgendamentoCancelado;
use Illuminate\Support\Facades\Event;
use Tests\FeatureBase;

/**
 * Testes da UNIFICAÇÃO: garantem que os módulos A (contas), B (agendamento) e C (painel)
 * conversam entre si com o mesmo esquema de banco e os mesmos perfis.
 */
class FluxoIntegradoTest extends FeatureBase
{
    private function pedido(int $endereco, int $servico): array
    {
        return [
            'endereco_id' => $endereco,
            'data_hora' => '2026-11-14T08:00:00-03:00',
            'servicos' => [['servico_id' => $servico, 'quantidade' => 1]],
        ];
    }

    public function test_cliente_agenda_equipe_confirma_e_o_atendente_fica_registrado(): void
    {
        $cliente = $this->criarCliente();
        $endereco = $this->criarEndereco($cliente);
        $servico = $this->criarServico(60, 100);
        $funcionario = $this->criarUsuario('funcionario');
        $this->criarJanela('2026-11-14', '08:00', '12:00');

        $id = $this->comoCliente($cliente)->postJson('/api/agendamentos', $this->pedido($endereco, $servico))
            ->assertStatus(201)
            ->json('data.id');

        $this->comoUsuario($funcionario)->getJson('/api/admin/agendamentos/resumo')
            ->assertStatus(200)
            ->assertJsonPath('data.pendentes', 1);

        $this->comoUsuario($funcionario)->patchJson("/api/admin/agendamentos/{$id}/confirmar")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmado')
            ->assertJsonMissingPath('data.cliente.senha_hash');

        $this->assertDatabaseHas('agendamento', [
            'id' => $id,
            'status' => 'confirmado',
            'usuario_id' => $funcionario,
        ]);
    }

    public function test_recusar_exige_motivo_e_libera_o_horario(): void
    {
        $cliente = $this->criarCliente();
        $endereco = $this->criarEndereco($cliente);
        $servico = $this->criarServico(60, 100);
        $admin = $this->criarUsuario('admin');
        $this->criarJanela('2026-11-14', '08:00', '12:00');

        $id = $this->comoCliente($cliente)->postJson('/api/agendamentos', $this->pedido($endereco, $servico))
            ->json('data.id');

        $this->comoUsuario($admin)->patchJson("/api/admin/agendamentos/{$id}/recusar", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');

        $this->comoUsuario($admin)->patchJson("/api/admin/agendamentos/{$id}/recusar", ['motivo' => 'Sem equipe nesse dia'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelado');

        $this->comoCliente($cliente)
            ->getJson("/api/disponibilidade?data=2026-11-14&servico_id={$servico}")
            ->assertJsonCount(7, 'data');
    }

    public function test_funcionario_ve_pedidos_mas_nao_gerencia_janelas(): void
    {
        $funcionario = $this->criarUsuario('funcionario');

        $this->comoUsuario($funcionario)->getJson('/api/admin/agendamentos')->assertStatus(200);

        $this->comoUsuario($funcionario)->getJson('/api/admin/disponibilidades')->assertStatus(403);
        $this->comoUsuario($funcionario)->postJson('/api/admin/disponibilidades/lote', [])->assertStatus(403);
    }

    public function test_admin_acessa_painel_e_janelas(): void
    {
        $admin = $this->criarUsuario('admin');

        $this->comoUsuario($admin)->getJson('/api/admin/agendamentos')->assertStatus(200);
        $this->comoUsuario($admin)->getJson('/api/admin/disponibilidades')->assertStatus(200);
    }

    public function test_cliente_nao_acessa_o_painel(): void
    {
        $cliente = $this->criarCliente();

        $this->comoCliente($cliente)->getJson('/api/admin/agendamentos')->assertStatus(403);
        $this->comoCliente($cliente)->getJson('/api/admin/agendamentos/resumo')->assertStatus(403);
    }

    public function test_cancelar_pelo_cliente_dispara_o_evento_e_registra_o_status(): void
    {
        Event::fake([AgendamentoCancelado::class]);
        $cliente = $this->criarCliente();
        $endereco = $this->criarEndereco($cliente);
        $servico = $this->criarServico(60, 100);
        $this->criarJanela('2026-11-14', '08:00', '12:00');

        $id = $this->comoCliente($cliente)->postJson('/api/agendamentos', $this->pedido($endereco, $servico))
            ->json('data.id');

        $this->comoCliente($cliente)->patchJson("/api/agendamentos/{$id}/cancelar")->assertStatus(200);

        Event::assertDispatched(AgendamentoCancelado::class);
        $this->assertDatabaseHas('agendamento', ['id' => $id, 'status' => 'cancelado']);
    }
}
