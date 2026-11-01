# Projeto unificado: Dev A + Dev B + Dev C

Este é **um único projeto Laravel** com os três módulos encaixados. A base é o projeto do Dev A
(branch `feature/enderecos`); os módulos de agendamento (Dev B) e do painel/Google (Dev C) foram
integrados a ele. **A partir daqui, todos trabalham neste mesmo projeto.**

> **Aviso:** a unificação foi feita por leitura e edição dos arquivos, **sem executar PHP** (não havia PHP
> no ambiente onde foi montada). Foram feitas verificações estáticas (chaves e parênteses balanceados, todas as
> classes `App\...` referenciadas existem, nenhum resto dos stubs de login). **A primeira execução real é o teste de verdade.**
> Se algo falhar, anote a mensagem completa e corrija no módulo do dono do arquivo.

## 1. Como rodar (primeira vez)

```bash
composer install
copy .env.example .env          # Windows (no Git Bash: cp .env.example .env)
php artisan key:generate
# crie o banco no MySQL (nome do .env: db_car_wash) e ajuste DB_* e ADMIN_* no .env
php artisan migrate:fresh --seed
php artisan serve
php artisan test                # SQLite em memória; não precisa de banco de teste no MySQL
```

- `migrate:fresh` é **obrigatório** para quem já tinha as migrations do Dev A: três migrations de criação foram alteradas (seção 4).
- Sem `GOOGLE_CALENDAR_ID`, em ambiente `local` ou de testes, o sistema usa a agenda vazia e **não chama o Google**.
  Em produção, a falta de configuração do Google continua gerando erro (503), como o Dev C definiu.
- Autenticação: token **Bearer** do Sanctum. Cliente: `POST /api/auth/register` e `/api/auth/login`.
  Equipe: `POST /api/admin/login`. Depois, enviar `Authorization: Bearer <token>`.

## 2. Quem é dono de quê

| Área | Dono | Onde |
|---|---|---|
| Contas, login, serviços, endereços, migrations, seeds | Dev A | `AuthController`s, `EnderecoController`, `ServicoController`, `database/` |
| Janelas, horários livres, agendamento do cliente | Dev B | `Controllers/Disponibilidade`, `Controllers/Agendamento/{Agendamento,Cancelamento}Controller`, `Services/Agendamento/{Calculadora,Disponibilidade,Agendamento}*`, `Models/{Agendamento,Disponibilidade,AgendamentoServico}` |
| Painel de pedidos, Google Agenda, fila | Dev C | `AdminAgendamentoController`, `Services/Agendamento/Google*`, `Listeners/`, `config/google_calendar.php` |

Contrato entre B e C: interface `App\Contracts\AgendaExterna` e os eventos `AgendamentoConfirmado`, `AgendamentoAtualizado`
e `AgendamentoCancelado`. **Não mudar nomes nem assinaturas sem avisar os outros.**

## 3. Rotas

| Rota | Quem acessa | Dono |
|---|---|---|
| `GET /api/servicos` | público | A |
| `POST /api/auth/register`, `/auth/login`; `POST /auth/logout`, `GET /auth/me` | público / cliente | A |
| `GET/POST/PUT/DELETE /api/enderecos` | cliente | A |
| `POST /api/admin/login`; `POST /admin/logout`, `GET /admin/me` | equipe | A |
| `GET /api/disponibilidade/dias`, `GET /api/disponibilidade` | cliente | B |
| `GET/POST /api/agendamentos`, `GET /agendamentos/{id}`, `PATCH /agendamentos/{id}/cancelar` | cliente | B |
| `GET /api/admin/agendamentos`, `/resumo`, `/{id}`; `PATCH .../confirmar`, `.../recusar`, `.../concluir` | **equipe** (admin ou funcionário) | C |
| `GET/POST/PUT/DELETE /api/admin/disponibilidades`, `POST .../lote` | **somente admin** | B |

Rotas de agendamento ficam em `routes/agendamento.php`, carregado por `routes/api.php`.

## 4. O que foi alterado para unir (arquivo por arquivo)

| Arquivo | Mudança | Por quê |
|---|---|---|
| `migrations/..._create_agendamento_table.php` | + `valor_total`, + `usuario_id` (FK `usuario`, `SET NULL`) | B grava o valor; C grava quem confirmou |
| `migrations/..._create_agendamento_servico_table.php` | + `quantidade` (padrão 1) | B grava a quantidade |
| `migrations/..._create_anexo_agendamento_table.php` | + `nome_original`, `mime_type`, `tamanho` (nulos) | O model do Dev A já usa essas colunas |
| `Models/Agendamento.php` | **Mesclado.** Status como **texto** com constantes; `fillable` com `valor_total`, `usuario_id`, `status`, `motivo`, `google_event_id`; relação `atendente()` para `User`; mantém `anexos()` e a limpeza de arquivos (A) e o gancho `AgendamentoAtualizado` (C) | B e C comparam o status com texto; o enum do A deixaria de funcionar |
| `Models/Disponibilidade.php` | `usuario_id` no `fillable`; escopo `doDia`, `inicioEm()` e `fimEm()` | Sem isso, quem cadastrou a janela era descartado |
| `Models/Servico.php` | `scopeAtivos`; pivot com `quantidade` | B |
| `Models/User.php` | `ehAdmin()` e `ehEquipe()` | Perfis |
| `Middleware/ExigePerfil.php` | `admin` agora exige `perfil = admin`; novo `equipe` (admin ou funcionário) | Antes, qualquer registro de `usuario` valia como admin |
| `routes/api.php` | `admin/logout` e `admin/me` usam `equipe`; carrega `routes/agendamento.php` | Funcionário continua conseguindo sair |
| `Providers/AppServiceProvider.php` | Junta A (limites de login) e C (bind do Google e listeners); sem Google em local/testes usa `AgendaExternaVazia` | Rodar e testar sem credenciais |
| `.env.example` | Variáveis `AGENDA_*` e `GOOGLE_*`; sem chaves duplicadas | Dev C tinha `QUEUE_CONNECTION` e `CACHE_STORE` repetidos |

**Removidos** (eram provisórios): `AuthStubLocal`, `ApenasAdmin`, o model `Usuario` e a migration de `disponibilidade` do Dev B
(vale a do Dev A). `Enums/StatusAgendamento.php` ficou no projeto, mas **não é mais usado**; o Dev A pode apagá-lo.

## 5. Decisões aplicadas (o líder pode reverter)

1. **Status como texto**, não enum.
2. **Dono do model `Agendamento`:** Dev B.
3. **Contrato de criação de agendamento:** o do Dev B (cliente logado, `endereco_id`, `data_hora`, lista de `servicos`).
4. **Funcionário:** vê e processa pedidos, mas não gerencia janelas.
5. **Google falhando:** continua 503 (decisão do Dev C); em local/testes sem credenciais usa agenda vazia.
6. **Sessão:** token Bearer (como o Dev A implementou).
7. **Tabela `foto`:** a do Dev A (`url` + `tipo`).

## 6. Documentação da API

- `docs/openapi_agendamento.yaml`: **vale** para disponibilidade, agendamentos e painel de pedidos.
- `docs/openapi.yaml` (Dev A): vale para contas, serviços e endereços. **Os caminhos de agendamento dele estão obsoletos**
  (descrevem o formulário público antigo). Deve ser atualizado pelo Dev A.

## 7. O que continua pendente (por dono)

- **Dev A:** rota para o admin redefinir a senha do cliente; documento de implantação (servidor, fila, backup);
  atualizar README e o OpenAPI; apagar o enum sem uso.
- **Dev C:** upload de anexos e fotos antes/depois (o esquema já existe); decisão sobre ajustar o valor ao confirmar;
  testes do painel e dos listeners; comando de reconciliação com o Google (opcional).
- **Dev B:** impedir reduzir a capacidade de uma janela abaixo dos agendamentos existentes; teste manual de pedidos simultâneos;
  `README` final do módulo (`docs/modulos/dev-b/`).
- **Todos:** rodar `php artisan test`, corrigir as falhas do primeiro ciclo e criar o repositório no GitHub.

## 8. Testes

- `tests/Feature/*Test.php` do Dev A (Pest) continuam como estavam.
- Dev B: `CalculoHorariosTest` (sem banco), `JanelasTest`, `DisponibilidadeTest`, `AgendamentoTest`, `CancelamentoTest`, `ConcorrenciaTest`,
  adaptados para o Sanctum e para o SQLite em memória (`tests/FeatureBase.php`).
  O `ConcorrenciaTest` envia os pedidos em sequência: pedidos realmente simultâneos exigem teste manual.
- **Novo:** `FluxoIntegradoTest` (cliente agenda → equipe confirma/recusa; perfis admin e funcionário).
- Dev C: `GoogleCalendarServiceTest`.
