<?php

namespace Tests;

use App\Models\Cliente;
use App\Models\Endereco;
use App\Models\Servico;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Base dos testes de disponibilidade e agendamento (módulos B e C).
 * Usa o mesmo banco de testes do projeto (SQLite em memória, com as migrations) e
 * autentica com tokens reais do Sanctum, como os testes do Dev A.
 */
abstract class FeatureBase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // "Agora" fixo: domingo, 01/11/2026, 09:00 (fuso do app).
        Carbon::setTestNow('2026-11-01 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------- dados de apoio

    protected function criarUsuario(string $perfil = 'admin'): int
    {
        return User::factory()->create(['perfil' => $perfil])->id;
    }

    protected function criarCliente(): int
    {
        return Cliente::factory()->create()->id;
    }

    protected function criarEndereco(int $clienteId): int
    {
        return Endereco::factory()->create(['cliente_id' => $clienteId])->id;
    }

    protected function criarServico(int $duracao = 60, float $preco = 100.00, bool $ativo = true): int
    {
        return Servico::create([
            'nome' => 'Servico Teste ' . $duracao,
            'categoria' => 'fachada',
            'duracao_minutos' => $duracao,
            'preco_base' => $preco,
            'ativo' => $ativo,
        ])->id;
    }

    protected function criarJanela(string $data, string $inicio, string $fim, int $capacidade = 1): int
    {
        return DB::table('disponibilidade')->insertGetId([
            'data' => $data,
            'hora_inicio' => $inicio,
            'hora_fim' => $fim,
            'capacidade' => $capacidade,
        ]);
    }

    protected function criarAgendamento(
        int $clienteId,
        int $enderecoId,
        string $inicio,
        string $fim,
        string $status = 'pendente'
    ): int {
        return DB::table('agendamento')->insertGetId([
            'cliente_id' => $clienteId,
            'endereco_id' => $enderecoId,
            'data_hora' => $inicio,
            'data_hora_fim' => $fim,
            'status' => $status,
            'valor_total' => 100.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------ login (tokens do Sanctum)

    protected function comoCliente(int $id)
    {
        $token = Cliente::findOrFail($id)->createToken('cliente')->plainTextToken;

        return $this->withToken($token)->withHeaders(['Accept' => 'application/json']);
    }

    protected function comoUsuario(int $id)
    {
        $token = User::findOrFail($id)->createToken('admin')->plainTextToken;

        return $this->withToken($token)->withHeaders(['Accept' => 'application/json']);
    }
}
