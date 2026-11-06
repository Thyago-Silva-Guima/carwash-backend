<?php

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function clienteParaEdicao(array $sobrescrever = []): Cliente
{
    return Cliente::factory()->create(array_merge([
        'nome' => 'Maria Souza',
        'telefone' => '41999999999',
        'email' => 'maria@teste.com',
        'senha_hash' => 'senha-correta-123',
    ], $sobrescrever));
}

function tokenDoCliente(Cliente $cliente, string $nomeDoToken = 'teste'): string
{
    return $cliente->createToken($nomeDoToken)->plainTextToken;
}

it('atualiza nome e telefone', function () {
    $cliente = clienteParaEdicao();

    $this->withToken(tokenDoCliente($cliente))->putJson('/api/auth/me', [
        'nome' => 'Maria Alves',
        'telefone' => '(41) 98888-7777',
    ])
        ->assertOk()
        ->assertJsonPath('nome', 'Maria Alves')
        ->assertJsonPath('telefone', '41988887777')
        ->assertJsonPath('email', 'maria@teste.com')
        ->assertJsonMissingPath('senha_hash');

    $cliente->refresh();

    expect($cliente->nome)->toBe('Maria Alves')
        ->and($cliente->telefone)->toBe('41988887777')
        ->and(Hash::check('senha-correta-123', $cliente->senha_hash))->toBeTrue();
});

it('altera só os campos enviados', function () {
    $cliente = clienteParaEdicao();

    $this->withToken(tokenDoCliente($cliente))
        ->putJson('/api/auth/me', ['telefone' => '41977776666'])
        ->assertOk();

    $cliente->refresh();

    expect($cliente->nome)->toBe('Maria Souza')
        ->and($cliente->telefone)->toBe('41977776666');
});

it('normaliza o e-mail e aceita manter o próprio', function () {
    $cliente = clienteParaEdicao();

    $this->withToken(tokenDoCliente($cliente))
        ->putJson('/api/auth/me', ['email' => '  MARIA@Teste.com '])
        ->assertOk()
        ->assertJsonPath('email', 'maria@teste.com');
});

it('rejeita e-mail que já pertence a outro cliente, ignorando maiúsculas', function () {
    clienteParaEdicao(['email' => 'outro@teste.com']);
    $cliente = clienteParaEdicao();

    $this->withToken(tokenDoCliente($cliente))
        ->putJson('/api/auth/me', ['email' => 'OUTRO@teste.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    expect($cliente->fresh()->email)->toBe('maria@teste.com');
});

it('valida telefone e nome', function () {
    $token = tokenDoCliente(clienteParaEdicao());

    $this->withToken($token)->putJson('/api/auth/me', ['telefone' => '12345'])
        ->assertStatus(422)->assertJsonValidationErrors('telefone');

    $this->withToken($token)->putJson('/api/auth/me', ['nome' => 'A'])
        ->assertStatus(422)->assertJsonValidationErrors('nome');

    $this->withToken($token)->putJson('/api/auth/me', ['nome' => '', 'telefone' => ''])
        ->assertStatus(422)->assertJsonValidationErrors(['nome', 'telefone']);
});

it('troca o e-mail sem senha atual e encerra as outras sessões', function () {
    $cliente = clienteParaEdicao();
    $tokenAtual = tokenDoCliente($cliente, 'celular');
    $tokenOutro = tokenDoCliente($cliente, 'notebook');

    $this->withToken($tokenAtual)->putJson('/api/auth/me', ['email' => 'nova@teste.com'])
        ->assertOk()
        ->assertJsonPath('email', 'nova@teste.com');

    expect($cliente->tokens()->count())->toBe(1);

    app('auth')->forgetGuards();
    $this->withToken($tokenAtual)->getJson('/api/auth/me')->assertOk();

    app('auth')->forgetGuards();
    $this->withToken($tokenOutro)->getJson('/api/auth/me')->assertUnauthorized();
});

it('troca a senha sem senha atual, valida o tamanho e encerra as outras sessões', function () {
    $cliente = clienteParaEdicao();
    $tokenAtual = tokenDoCliente($cliente, 'celular');
    $tokenOutro = tokenDoCliente($cliente, 'notebook');

    $this->withToken($tokenAtual)->putJson('/api/auth/me', ['senha' => 'curta'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('senha');

    $this->withToken($tokenAtual)->putJson('/api/auth/me', ['senha' => 'nova-senha-456'])
        ->assertOk();

    $cliente->refresh();

    expect(Hash::check('nova-senha-456', $cliente->senha_hash))->toBeTrue()
        ->and(Hash::check('senha-correta-123', $cliente->senha_hash))->toBeFalse()
        ->and($cliente->tokens()->count())->toBe(1);

    app('auth')->forgetGuards();
    $this->withToken($tokenAtual)->getJson('/api/auth/me')->assertOk();

    app('auth')->forgetGuards();
    $this->withToken($tokenOutro)->getJson('/api/auth/me')->assertUnauthorized();

    $this->postJson('/api/auth/login', [
        'email' => 'maria@teste.com',
        'password' => 'nova-senha-456',
    ])->assertOk();
});

it('não altera campos que não fazem parte do perfil editável', function () {
    $cliente = clienteParaEdicao(['tipo' => 'residencial']);

    $this->withToken(tokenDoCliente($cliente))->putJson('/api/auth/me', [
        'id' => 999,
        'tipo' => 'comercial',
        'aceite_termos_em' => null,
        'nome' => 'Maria Alves',
    ])
        ->assertOk()
        ->assertJsonPath('nome', 'Maria Alves');

    $cliente->refresh();

    expect($cliente->tipo)->toBe('residencial')
        ->and($cliente->id)->not->toBe(999)
        ->and($cliente->aceite_termos_em)->not->toBeNull();
});

it('exige token de cliente', function () {
    $this->putJson('/api/auth/me', ['nome' => 'Visitante'])->assertUnauthorized();

    $admin = User::factory()->create(['perfil' => 'admin']);

    $this->withToken($admin->createToken('admin')->plainTextToken)
        ->putJson('/api/auth/me', ['nome' => 'Admin Editado'])
        ->assertForbidden();
});
