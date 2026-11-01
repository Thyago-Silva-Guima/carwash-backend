<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

function novoAdmin(): User
{
    return User::factory()->create([
        'email' => 'admin@teste.com',
        'senha_hash' => 'senha-correta-123',
    ]);
}

it('devolve um token ao logar com credenciais válidas', function () {
    $admin = novoAdmin();

    $this->postJson('/api/admin/login', ['email' => 'admin@teste.com', 'password' => 'senha-correta-123'])
        ->assertOk()
        ->assertJsonStructure(['token', 'usuario' => ['id', 'nome', 'email', 'perfil']])
        ->assertJsonMissingPath('usuario.senha_hash');

    expect($admin->tokens()->count())->toBe(1);
});

it('não cria token e responde igual para e-mail inexistente e senha errada', function () {
    novoAdmin();

    $senhaErrada = $this->postJson('/api/admin/login', ['email' => 'admin@teste.com', 'password' => 'errada']);
    $emailInexistente = $this->postJson('/api/admin/login', ['email' => 'outro@teste.com', 'password' => 'qualquer']);

    $senhaErrada->assertStatus(422);
    $emailInexistente->assertStatus(422);
    expect($emailInexistente->json())->toBe($senhaErrada->json());
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('mantém só o token do último login', function () {
    $admin = novoAdmin();
    $dados = ['email' => 'admin@teste.com', 'password' => 'senha-correta-123'];

    $this->postJson('/api/admin/login', $dados)->assertOk();
    $this->postJson('/api/admin/login', $dados)->assertOk();

    expect($admin->tokens()->count())->toBe(1);
});

it('devolve os dados do admin logado em /admin/me', function () {
    novoAdmin();
    $token = $this->postJson('/api/admin/login', ['email' => 'admin@teste.com', 'password' => 'senha-correta-123'])->json('token');

    $this->withToken($token)->getJson('/api/admin/me')
        ->assertOk()
        ->assertJsonStructure(['id', 'nome', 'email', 'perfil'])
        ->assertJsonMissingPath('senha_hash');
});

it('revoga o token atual no logout', function () {
    $admin = novoAdmin();
    $token = $this->postJson('/api/admin/login', ['email' => 'admin@teste.com', 'password' => 'senha-correta-123'])->json('token');

    $this->withToken($token)->postJson('/api/admin/logout')
        ->assertOk()
        ->assertJson(['message' => 'Sessão encerrada']);

    expect($admin->tokens()->count())->toBe(0);
});

it('exige token para fazer logout', function () {
    $this->postJson('/api/admin/logout')
        ->assertUnauthorized()
        ->assertJson(['message' => 'Não autenticado.']);
});

it('permite logar com o admin criado pelo seeder', function () {
    config(['admin.email' => 'seed@teste.com', 'admin.password' => 'senha-segura-123']);

    $this->seed(DatabaseSeeder::class);

    $this->postJson('/api/admin/login', ['email' => 'seed@teste.com', 'password' => 'senha-segura-123'])
        ->assertOk()
        ->assertJsonStructure(['token']);
});
