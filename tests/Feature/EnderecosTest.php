<?php

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Endereco;
use App\Models\User;

function dadosEndereco(array $sobrescrever = []): array
{
    return array_merge([
        'rua' => 'Rua das Flores',
        'numero' => '123',
        'bairro' => 'Centro',
        'cidade' => 'Curitiba',
        'cep' => '80010-000',
    ], $sobrescrever);
}

/**
 * @return array{0: Cliente, 1: string}
 */
function clienteAutenticado(): array
{
    $cliente = Cliente::factory()->create();

    return [$cliente, $cliente->createToken('cliente')->plainTextToken];
}

it('exige token em todas as rotas de endereços', function () {
    $this->getJson('/api/enderecos')->assertUnauthorized();
    $this->postJson('/api/enderecos', dadosEndereco())->assertUnauthorized();
    $this->getJson('/api/enderecos/1')->assertUnauthorized();
    $this->putJson('/api/enderecos/1', dadosEndereco())->assertUnauthorized();
    $this->deleteJson('/api/enderecos/1')->assertUnauthorized();
});

it('não deixa o token da equipe acessar os endereços do cliente', function () {
    $token = User::factory()->create()->createToken('admin')->plainTextToken;

    $this->withToken($token)->getJson('/api/enderecos')
        ->assertForbidden()
        ->assertJson(['message' => 'Acesso negado.']);

    $this->withToken($token)->postJson('/api/enderecos', dadosEndereco())->assertForbidden();
});

it('cadastra o endereço para o cliente logado e normaliza o CEP', function () {
    [$cliente, $token] = clienteAutenticado();

    $this->withToken($token)->postJson('/api/enderecos', dadosEndereco())
        ->assertCreated()
        ->assertJsonStructure(['id', 'rua', 'numero', 'bairro', 'complemento', 'cidade', 'cep', 'endereco_completo'])
        ->assertJsonPath('cep', '80010000')
        ->assertJsonPath('complemento', null)
        ->assertJsonPath('endereco_completo', 'Rua das Flores, 123 - Centro, Curitiba - CEP 80010-000')
        ->assertJsonMissingPath('cliente_id');

    $this->assertDatabaseHas('endereco', [
        'cliente_id' => $cliente->id,
        'rua' => 'Rua das Flores',
        'cep' => '80010000',
    ]);
});

it('guarda o complemento quando informado', function () {
    [, $token] = clienteAutenticado();

    $this->withToken($token)->postJson('/api/enderecos', dadosEndereco(['complemento' => 'Apto 12']))
        ->assertCreated()
        ->assertJsonPath('complemento', 'Apto 12')
        ->assertJsonPath('endereco_completo', 'Rua das Flores, 123, Apto 12 - Centro, Curitiba - CEP 80010-000');
});

it('aceita o número como inteiro', function () {
    [, $token] = clienteAutenticado();

    $this->withToken($token)->postJson('/api/enderecos', dadosEndereco(['numero' => 123]))
        ->assertCreated()
        ->assertJsonPath('numero', '123');
});

it('valida os campos obrigatórios', function () {
    [, $token] = clienteAutenticado();

    $this->withToken($token)->postJson('/api/enderecos', [])
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors'])
        ->assertJsonValidationErrors(['rua', 'numero', 'bairro', 'cidade', 'cep']);
});

it('rejeita CEP que não tem 8 dígitos', function () {
    [, $token] = clienteAutenticado();

    $this->withToken($token)->postJson('/api/enderecos', dadosEndereco(['cep' => '123']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cep']);
});

it('lista só os endereços do cliente logado, em ordem de id', function () {
    [$cliente, $token] = clienteAutenticado();
    $outro = Cliente::factory()->create();

    $primeiro = Endereco::factory()->create(['cliente_id' => $cliente->id]);
    $segundo = Endereco::factory()->create(['cliente_id' => $cliente->id]);
    Endereco::factory()->create(['cliente_id' => $outro->id]);

    $this->withToken($token)->getJson('/api/enderecos')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', $primeiro->id)
        ->assertJsonPath('1.id', $segundo->id);
});

it('devolve lista vazia quando o cliente não tem endereços', function () {
    [, $token] = clienteAutenticado();

    $this->withToken($token)->getJson('/api/enderecos')
        ->assertOk()
        ->assertExactJson([]);
});

it('mostra um endereço do cliente', function () {
    [$cliente, $token] = clienteAutenticado();
    $endereco = Endereco::factory()->create(['cliente_id' => $cliente->id, 'rua' => 'Rua Teste']);

    $this->withToken($token)->getJson("/api/enderecos/{$endereco->id}")
        ->assertOk()
        ->assertJsonPath('id', $endereco->id)
        ->assertJsonPath('rua', 'Rua Teste');
});

it('responde 404 para endereço de outro cliente em todas as rotas', function () {
    [, $token] = clienteAutenticado();
    $dele = Endereco::factory()->create(['rua' => 'Rua Original']);

    $this->withToken($token)->getJson("/api/enderecos/{$dele->id}")->assertNotFound();
    $this->withToken($token)->putJson("/api/enderecos/{$dele->id}", dadosEndereco())->assertNotFound();
    $this->withToken($token)->deleteJson("/api/enderecos/{$dele->id}")->assertNotFound();

    $this->assertDatabaseHas('endereco', ['id' => $dele->id, 'rua' => 'Rua Original']);
});

it('responde 404 para id inexistente ou não numérico', function () {
    [, $token] = clienteAutenticado();

    $this->withToken($token)->getJson('/api/enderecos/999')
        ->assertNotFound()
        ->assertJson(['message' => 'Recurso não encontrado.']);

    $this->withToken($token)->getJson('/api/enderecos/abc')->assertNotFound();
});

it('atualiza o endereço e limpa o complemento quando não enviado', function () {
    [$cliente, $token] = clienteAutenticado();
    $endereco = Endereco::factory()->create([
        'cliente_id' => $cliente->id,
        'complemento' => 'Casa 2',
    ]);

    $this->withToken($token)->putJson("/api/enderecos/{$endereco->id}", dadosEndereco([
        'rua' => 'Rua Nova',
        'cep' => '81000-111',
    ]))
        ->assertOk()
        ->assertJsonPath('rua', 'Rua Nova')
        ->assertJsonPath('cep', '81000111')
        ->assertJsonPath('complemento', null);

    $this->assertDatabaseHas('endereco', [
        'id' => $endereco->id,
        'cliente_id' => $cliente->id,
        'rua' => 'Rua Nova',
        'complemento' => null,
    ]);
});

it('valida os dados ao atualizar', function () {
    [$cliente, $token] = clienteAutenticado();
    $endereco = Endereco::factory()->create(['cliente_id' => $cliente->id]);

    $this->withToken($token)->putJson("/api/enderecos/{$endereco->id}", ['rua' => 'X'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rua', 'numero', 'bairro', 'cidade', 'cep']);
});

it('remove um endereço sem agendamentos', function () {
    [$cliente, $token] = clienteAutenticado();
    $endereco = Endereco::factory()->create(['cliente_id' => $cliente->id]);

    $this->withToken($token)->deleteJson("/api/enderecos/{$endereco->id}")->assertNoContent();

    $this->assertDatabaseMissing('endereco', ['id' => $endereco->id]);
});

it('não remove endereço usado em agendamento', function () {
    [$cliente, $token] = clienteAutenticado();
    $endereco = Endereco::factory()->create(['cliente_id' => $cliente->id]);

    Agendamento::create([
        'cliente_id' => $cliente->id,
        'endereco_id' => $endereco->id,
        'data_hora' => now()->addDay(),
        'data_hora_fim' => now()->addDay()->addHour(),
    ]);

    $this->withToken($token)->deleteJson("/api/enderecos/{$endereco->id}")
        ->assertStatus(409)
        ->assertJson(['message' => 'Este endereço já foi usado em agendamentos e não pode ser removido.']);

    $this->assertDatabaseHas('endereco', ['id' => $endereco->id]);
});
