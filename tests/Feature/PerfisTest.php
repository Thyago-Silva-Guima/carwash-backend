<?php

use App\Models\Cliente;
use App\Models\User;

it('não deixa o token de cliente acessar rotas da equipe', function () {
    $token = Cliente::factory()->create()->createToken('cliente')->plainTextToken;

    $this->withToken($token)->getJson('/api/admin/me')
        ->assertForbidden()
        ->assertJson(['message' => 'Acesso negado.']);

    $this->withToken($token)->postJson('/api/admin/logout')->assertForbidden();
});

it('não deixa o token da equipe acessar rotas de cliente', function () {
    $token = User::factory()->create()->createToken('admin')->plainTextToken;

    $this->withToken($token)->getJson('/api/auth/me')
        ->assertForbidden()
        ->assertJson(['message' => 'Acesso negado.']);

    $this->withToken($token)->postJson('/api/auth/logout')->assertForbidden();
});

it('resolve o token de cliente como cliente mesmo com id igual ao da equipe', function () {
    $cliente = Cliente::factory()->create(['nome' => 'Conta Cliente']);
    $admin = User::factory()->create(['nome' => 'Conta Equipe']);

    expect($cliente->id)->toBe($admin->id);

    $this->withToken($cliente->createToken('cliente')->plainTextToken)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('nome', 'Conta Cliente');
});

it('resolve o token da equipe como equipe mesmo com id igual ao de um cliente', function () {
    $cliente = Cliente::factory()->create(['nome' => 'Conta Cliente']);
    $admin = User::factory()->create(['nome' => 'Conta Equipe']);

    expect($cliente->id)->toBe($admin->id);

    $this->withToken($admin->createToken('admin')->plainTextToken)
        ->getJson('/api/admin/me')
        ->assertOk()
        ->assertJsonPath('nome', 'Conta Equipe');
});

it('devolve 401 sem token nas rotas protegidas', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
    $this->getJson('/api/admin/me')->assertUnauthorized();
});
