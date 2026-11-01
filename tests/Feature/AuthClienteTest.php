<?php

use App\Models\Cliente;
use Illuminate\Support\Facades\Hash;

function dadosCadastro(array $sobrescrever = []): array
{
    return array_merge([
        'nome' => 'Maria Souza',
        'telefone' => '(41) 99999-9999',
        'email' => 'Maria@Teste.com',
        'password' => 'senha-correta-123',
        'aceite_termos' => true,
    ], $sobrescrever);
}

function novoCliente(): Cliente
{
    return Cliente::factory()->create([
        'email' => 'cliente@teste.com',
        'senha_hash' => 'senha-correta-123',
    ]);
}

it('cadastra o cliente, devolve token e guarda a senha em hash', function () {
    $this->postJson('/api/auth/register', dadosCadastro())
        ->assertCreated()
        ->assertJsonStructure(['token', 'cliente' => ['id', 'nome', 'email', 'telefone']])
        ->assertJsonPath('cliente.email', 'maria@teste.com')
        ->assertJsonPath('cliente.telefone', '41999999999')
        ->assertJsonMissingPath('cliente.senha_hash');

    $cliente = Cliente::firstOrFail();

    expect($cliente->senha_hash)->not->toBe('senha-correta-123')
        ->and(Hash::check('senha-correta-123', $cliente->senha_hash))->toBeTrue()
        ->and($cliente->aceite_termos_em)->not->toBeNull()
        ->and($cliente->tokens()->count())->toBe(1);
});

it('não cadastra sem aceitar os termos', function () {
    $this->postJson('/api/auth/register', dadosCadastro(['aceite_termos' => false]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('aceite_termos');

    $this->postJson('/api/auth/register', array_diff_key(dadosCadastro(), ['aceite_termos' => 1]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('aceite_termos');

    expect(Cliente::count())->toBe(0);
});

it('rejeita e-mail já cadastrado, ignorando maiúsculas', function () {
    novoCliente();

    $this->postJson('/api/auth/register', dadosCadastro(['email' => 'CLIENTE@teste.com']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('valida telefone, senha e campos obrigatórios', function () {
    $this->postJson('/api/auth/register', dadosCadastro(['telefone' => '12345']))
        ->assertStatus(422)->assertJsonValidationErrors('telefone');

    $this->postJson('/api/auth/register', dadosCadastro(['password' => 'curta']))
        ->assertStatus(422)->assertJsonValidationErrors('password');

    $this->postJson('/api/auth/register', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nome', 'telefone', 'email', 'password', 'aceite_termos']);
});

it('devolve um token ao logar com credenciais válidas', function () {
    $cliente = novoCliente();

    $this->postJson('/api/auth/login', ['email' => 'cliente@teste.com', 'password' => 'senha-correta-123'])
        ->assertOk()
        ->assertJsonStructure(['token', 'cliente' => ['id', 'nome', 'email', 'telefone']]);

    expect($cliente->tokens()->count())->toBe(1);
});

it('responde igual para e-mail inexistente e senha errada', function () {
    novoCliente();

    $senhaErrada = $this->postJson('/api/auth/login', ['email' => 'cliente@teste.com', 'password' => 'errada']);
    $emailInexistente = $this->postJson('/api/auth/login', ['email' => 'outro@teste.com', 'password' => 'qualquer']);

    $senhaErrada->assertStatus(422);
    expect($emailInexistente->json())->toBe($senhaErrada->json());
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('devolve os dados do cliente logado em /auth/me', function () {
    novoCliente();
    $token = $this->postJson('/api/auth/login', ['email' => 'cliente@teste.com', 'password' => 'senha-correta-123'])->json('token');

    $this->withToken($token)->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('email', 'cliente@teste.com')
        ->assertJsonMissingPath('senha_hash');
});

it('revoga o token atual no logout', function () {
    $cliente = novoCliente();
    $token = $this->postJson('/api/auth/login', ['email' => 'cliente@teste.com', 'password' => 'senha-correta-123'])->json('token');

    $this->withToken($token)->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJson(['message' => 'Sessão encerrada']);

    expect($cliente->tokens()->count())->toBe(0);
});

it('limita os cadastros por IP', function () {
    foreach (range(1, 10) as $i) {
        $this->postJson('/api/auth/register', dadosCadastro(['email' => "c{$i}@teste.com"]))->assertCreated();
    }

    $this->postJson('/api/auth/register', dadosCadastro(['email' => 'c11@teste.com']))
        ->assertStatus(429)
        ->assertJsonStructure(['message', 'retry_after']);
});
