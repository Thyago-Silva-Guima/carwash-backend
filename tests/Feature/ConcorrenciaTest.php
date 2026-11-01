<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\FeatureBase;

/**
 * ATENÇÃO: estes testes enviam os pedidos UM DEPOIS DO OUTRO. Eles provam que a
 * regra de capacidade funciona, mas NÃO provam o bloqueio de pedidos realmente
 * simultâneos (lockForUpdate). Esse caso exige um teste manual: dois pedidos
 * enviados quase ao mesmo tempo para o mesmo horário.
 */
class ConcorrenciaTest extends FeatureBase
{
    private function dados(int $endereco, int $servico, string $dataHora = '2026-11-14T08:00:00-03:00'): array
    {
        return [
            'endereco_id' => $endereco,
            'data_hora' => $dataHora,
            'servicos' => [['servico_id' => $servico, 'quantidade' => 1]],
        ];
    }

    /** @return array{0:int,1:int} [clienteId, enderecoId] */
    private function novoCliente(): array
    {
        $cliente = $this->criarCliente();

        return [$cliente, $this->criarEndereco($cliente)];
    }

    public function test_dois_clientes_no_mesmo_horario_so_o_primeiro_passa(): void
    {
        $servico = $this->criarServico(60);
        $this->criarJanela('2026-11-14', '08:00', '12:00');
        [$a, $enderecoA] = $this->novoCliente();
        [$b, $enderecoB] = $this->novoCliente();

        $this->comoCliente($a)->postJson('/api/agendamentos', $this->dados($enderecoA, $servico))->assertStatus(201);
        $this->comoCliente($b)->postJson('/api/agendamentos', $this->dados($enderecoB, $servico))
            ->assertStatus(422)
            ->assertJsonValidationErrors('data_hora');

        $this->assertSame(1, DB::table('agendamento')->count());
    }

    public function test_horario_parcialmente_sobreposto_tambem_e_recusado(): void
    {
        $servico = $this->criarServico(60);
        $this->criarJanela('2026-11-14', '08:00', '12:00');
        [$a, $enderecoA] = $this->novoCliente();
        [$b, $enderecoB] = $this->novoCliente();

        $this->comoCliente($a)->postJson('/api/agendamentos', $this->dados($enderecoA, $servico))->assertStatus(201);

        // 08:30 a 09:30 colide com 08:00 a 09:00.
        $this->comoCliente($b)->postJson('/api/agendamentos', $this->dados($enderecoB, $servico, '2026-11-14T08:30:00-03:00'))
            ->assertStatus(422);

        // 09:00 a 10:00 não colide (intervalos semiabertos).
        $this->comoCliente($b)->postJson('/api/agendamentos', $this->dados($enderecoB, $servico, '2026-11-14T09:00:00-03:00'))
            ->assertStatus(201);
    }

    public function test_janela_com_capacidade_2_aceita_dois_e_recusa_o_terceiro(): void
    {
        $servico = $this->criarServico(60);
        $this->criarJanela('2026-11-14', '08:00', '12:00', 2);
        [$a, $enderecoA] = $this->novoCliente();
        [$b, $enderecoB] = $this->novoCliente();
        [$c, $enderecoC] = $this->novoCliente();

        $this->comoCliente($a)->postJson('/api/agendamentos', $this->dados($enderecoA, $servico))->assertStatus(201);
        $this->comoCliente($b)->postJson('/api/agendamentos', $this->dados($enderecoB, $servico))->assertStatus(201);
        $this->comoCliente($c)->postJson('/api/agendamentos', $this->dados($enderecoC, $servico))->assertStatus(422);

        $this->assertSame(2, DB::table('agendamento')->count());
    }
}
