<?php

use App\Models\User;

it('devolve 422 em JSON no formato padrão', function () {
    $this->postJson('/api/admin/login', [])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['email', 'password']]);
});

it('devolve JSON mesmo sem o header Accept', function () {
    $this->post('/api/admin/login', [])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});

it('devolve 401 em JSON nas rotas protegidas sem token', function () {
    $this->post('/api/admin/logout')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Não autenticado.']);
});

it('devolve 404 em JSON para rota inexistente', function () {
    $this->getJson('/api/nao-existe')
        ->assertNotFound()
        ->assertJson(['message' => 'Recurso não encontrado.']);
});

it('rejeita login inválido no formato 422 padrão', function () {
    User::factory()->create(['email' => 'admin@teste.com', 'senha_hash' => 'senha-correta-123']);

    $this->postJson('/api/admin/login', ['email' => 'admin@teste.com', 'password' => 'errada'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('bloqueia o login na 6ª tentativa do mesmo minuto', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/admin/login', ['email' => 'a@b.com', 'password' => 'x'])->assertStatus(422);
    }

    $this->postJson('/api/admin/login', ['email' => 'a@b.com', 'password' => 'x'])
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonStructure(['message', 'retry_after']);
});

it('libera o CORS para a origem do front', function () {
    $origem = config('cors.allowed_origins')[0];

    $this->withHeaders(['Origin' => $origem, 'Access-Control-Request-Method' => 'POST'])
        ->options('/api/admin/login')
        ->assertHeader('Access-Control-Allow-Origin', $origem);

    $falsa = $this->withHeaders(['Origin' => 'http://site-falso.com', 'Access-Control-Request-Method' => 'POST'])
        ->options('/api/admin/login');

    expect($falsa->headers->get('Access-Control-Allow-Origin'))->not->toBe('http://site-falso.com');
});