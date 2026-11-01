<?php

namespace Tests\Feature;

use App\Events\AgendamentoCancelado;
use Illuminate\Support\Facades\Event;
use Tests\FeatureBase;

class CancelamentoTest extends FeatureBase
{
    private function cenario(): array
    {
        $cliente = $this->criarCliente();
        $endereco = $this->criarEndereco($cliente);
        $servico = $this->criarServico(60, 100);
        $this->criarJanela('2026-11-14', '08:00', '12:00');

        return [$cliente, $endereco, $servico];
    }

    public function test_cliente_cancela_com_antecedencia_e_dispara_o_evento(): void
    {
        Event::fake([AgendamentoCancelado::class]);
        [$cliente, $endereco] = $this->cenario();
        $id = $this->criarAgendamento($cliente, $endereco, '2026-11-14 08:00:00', '2026-11-14 09:00:00');

        $this->comoCliente($cliente)->patchJson("/api/agendamentos/{$id}/cancelar")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelado');

        $this->assertDatabaseHas('agendamento', [
            'id' => $id,
            'status' => 'cancelado',
            'motivo' => 'Cancelado pelo cliente',
        ]);
        Event::assertDispatched(AgendamentoCancelado::class);
    }

    public function test_menos_de_2_horas_antes_retorna_422(): void
    {
        [$cliente, $endereco] = $this->cenario();
        // "Agora" nos testes é 01/11 às 09:00; o limite para cancelar seria 08:00.
        $id = $this->criarAgendamento($cliente, $endereco, '2026-11-01 10:00:00', '2026-11-01 11:00:00');

        $this->comoCliente($cliente)->patchJson("/api/agendamentos/{$id}/cancelar")
            ->assertStatus(422)
            ->assertJsonValidationErrors('agendamento');

        $this->assertDatabaseHas('agendamento', ['id' => $id, 'status' => 'pendente']);
    }

    public function test_agendamento_ja_cancelado_retorna_409(): void
    {
        [$cliente, $endereco] = $this->cenario();
        $id = $this->criarAgendamento($cliente, $endereco, '2026-11-14 08:00:00', '2026-11-14 09:00:00', 'cancelado');

        $this->comoCliente($cliente)->patchJson("/api/agendamentos/{$id}/cancelar")->assertStatus(409);
    }

    public function test_agendamento_concluido_retorna_409(): void
    {
        [$cliente, $endereco] = $this->cenario();
        $id = $this->criarAgendamento($cliente, $endereco, '2026-11-14 08:00:00', '2026-11-14 09:00:00', 'concluido');

        $this->comoCliente($cliente)->patchJson("/api/agendamentos/{$id}/cancelar")->assertStatus(409);
    }

    public function test_cliente_nao_cancela_agendamento_de_outro(): void
    {
        [$cliente, $endereco] = $this->cenario();
        $outro = $this->criarCliente();
        $id = $this->criarAgendamento($cliente, $endereco, '2026-11-14 08:00:00', '2026-11-14 09:00:00');

        $this->comoCliente($outro)->patchJson("/api/agendamentos/{$id}/cancelar")->assertStatus(404);

        $this->assertDatabaseHas('agendamento', ['id' => $id, 'status' => 'pendente']);
    }

    public function test_cancelar_libera_o_horario(): void
    {
        [$cliente, $endereco, $servico] = $this->cenario();
        $id = $this->criarAgendamento($cliente, $endereco, '2026-11-14 08:00:00', '2026-11-14 09:00:00');
        $consulta = "/api/disponibilidade?data=2026-11-14&servico_id={$servico}";

        $this->comoCliente($cliente)->getJson($consulta)->assertJsonCount(5, 'data');

        $this->comoCliente($cliente)->patchJson("/api/agendamentos/{$id}/cancelar")->assertStatus(200);

        $this->comoCliente($cliente)->getJson($consulta)->assertJsonCount(7, 'data');
    }
}
