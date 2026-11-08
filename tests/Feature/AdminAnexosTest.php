<?php

namespace Tests\Feature;

use App\Models\AgendamentoAnexo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\FeatureBase;

class AdminAnexosTest extends FeatureBase
{
    private function agendamentoComAnexo(): array
    {
        $cliente = $this->criarCliente();
        $endereco = $this->criarEndereco($cliente);
        $id = $this->criarAgendamento($cliente, $endereco, '2026-11-10 09:00:00', '2026-11-10 10:00:00');

        $anexo = AgendamentoAnexo::create([
            'agendamento_id' => $id,
            'url' => "anexos/{$id}/foto.jpg",
            'nome_original' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'tamanho' => 1234,
        ]);

        return [$id, $anexo];
    }

    public function test_listagem_admin_traz_os_metadados_dos_anexos(): void
    {
        [$id, $anexo] = $this->agendamentoComAnexo();
        $funcionario = $this->criarUsuario('funcionario');

        $resposta = $this->comoUsuario($funcionario)->getJson('/api/admin/agendamentos')->assertStatus(200);

        $item = collect($resposta->json('data'))->firstWhere('id', $id);

        $this->assertCount(1, $item['anexos']);
        $this->assertSame([
            'id' => $anexo->id,
            'nome' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'tamanho' => 1234,
        ], $item['anexos'][0]);
    }

    public function test_detalhe_admin_traz_os_anexos_sem_expor_o_caminho_interno(): void
    {
        [$id] = $this->agendamentoComAnexo();
        $admin = $this->criarUsuario('admin');

        $resposta = $this->comoUsuario($admin)->getJson("/api/admin/agendamentos/{$id}")->assertStatus(200);

        $this->assertCount(1, $resposta->json('data.anexos'));
        $this->assertStringNotContainsString("anexos/{$id}/foto.jpg", $resposta->getContent());
    }

    public function test_equipe_baixa_o_arquivo_do_anexo(): void
    {
        Storage::fake('local');
        [, $anexo] = $this->agendamentoComAnexo();
        Storage::disk('local')->put($anexo->url, 'conteudo');

        foreach (['admin', 'funcionario'] as $perfil) {
            $usuario = $this->criarUsuario($perfil);

            $resposta = $this->comoUsuario($usuario)->get("/api/admin/anexos/{$anexo->id}")
                ->assertStatus(200)
                ->assertHeader('X-Content-Type-Options', 'nosniff');

            $this->assertStringContainsString('image/jpeg', $resposta->headers->get('Content-Type'));
        }
    }

    public function test_download_do_anexo_exige_login(): void
    {
        [, $anexo] = $this->agendamentoComAnexo();

        $this->getJson("/api/admin/anexos/{$anexo->id}")->assertStatus(401);
    }

    public function test_cliente_nao_baixa_anexo_pela_rota_administrativa(): void
    {
        Storage::fake('local');
        [, $anexo] = $this->agendamentoComAnexo();
        Storage::disk('local')->put($anexo->url, 'conteudo');
        $cliente = $this->criarCliente();

        $this->comoCliente($cliente)->getJson("/api/admin/anexos/{$anexo->id}")->assertStatus(403);
    }

    public function test_anexo_inexistente_ou_sem_arquivo_no_disco_retorna_404(): void
    {
        Storage::fake('local');
        [, $anexo] = $this->agendamentoComAnexo(); // registro existe, arquivo não
        $admin = $this->criarUsuario('admin');

        $this->comoUsuario($admin)->getJson("/api/admin/anexos/{$anexo->id}")->assertStatus(404);
        $this->comoUsuario($admin)->getJson('/api/admin/anexos/999999')->assertStatus(404);
    }

    public function test_a_rota_publica_antiga_de_anexos_nao_existe_mais(): void
    {
        Storage::fake('local');
        [, $anexo] = $this->agendamentoComAnexo();
        Storage::disk('local')->put($anexo->url, 'conteudo');

        $this->getJson("/api/anexos/{$anexo->id}")->assertStatus(404);
    }

    public function test_anexo_enviado_pelo_cliente_aparece_no_painel_e_pode_ser_baixado(): void
    {
        Storage::fake('local');
        $cliente = $this->criarCliente();
        $endereco = $this->criarEndereco($cliente);
        $servico = $this->criarServico(60, 100);
        $this->criarJanela('2026-11-14', '08:00', '12:00');
        $admin = $this->criarUsuario('admin');

        $id = $this->comoCliente($cliente)->post('/api/agendamentos', [
            'endereco_id' => $endereco,
            'data_hora' => '2026-11-14T08:00:00-03:00',
            'servicos' => [['servico_id' => $servico, 'quantidade' => 1]],
            'anexos' => [UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg')],
        ])->assertStatus(201)->json('data.id');

        $anexo = $this->comoUsuario($admin)->getJson("/api/admin/agendamentos/{$id}")
            ->assertStatus(200)
            ->json('data.anexos.0');

        $this->assertSame('foto.jpg', $anexo['nome']);

        $this->comoUsuario($admin)->get("/api/admin/anexos/{$anexo['id']}")->assertStatus(200);
    }
}