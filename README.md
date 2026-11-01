# Car Wash - Back-end

> **Projeto unificado (Dev A + B + C).** Leia primeiro o [INTEGRACAO.md](INTEGRACAO.md): como rodar, quem é dono de quê, o que foi alterado e o que falta.

API REST de agendamentos para uma empresa de lavagem de áreas externas (muros, portões, fachadas, calçadas, garagens etc.), feita em **Laravel 13** com autenticação por token via **Laravel Sanctum**. Todas as respostas são em JSON e as mensagens de validação estão em português (`pt_BR`).

> Este README descreve o **estado atual do código**. Veja a seção [Estado atual](#estado-atual) para saber o que já está implementado e o que existe apenas no contrato OpenAPI.

## Sumário

- [Estado atual](#estado-atual)
- [Stack](#stack)
- [Requisitos](#requisitos)
- [Instalação passo a passo](#instalação-passo-a-passo)
- [Como copiar o `.env`](#como-copiar-o-env)
- [Variáveis do `.env`](#variáveis-do-env)
- [Banco de dados e migrations](#banco-de-dados-e-migrations)
- [Seeders (serviços e admin)](#seeders-serviços-e-admin)
- [Como rodar o servidor](#como-rodar-o-servidor)
- [Endpoints implementados](#endpoints-implementados)
- [Documentação OpenAPI e mock da API](#documentação-openapi-e-mock-da-api)
- [Testes](#testes)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Problemas comuns](#problemas-comuns)
- [Licença](#licença)

---

## Estado atual

| Área | Situação |
| --- | --- |
| Login e logout do admin (Sanctum) | Implementado e testado |
| `GET /api/servicos` (lista pública de serviços ativos) | Implementado e testado |
| Rate limit no login, CORS, erros padronizados em JSON | Implementado e testado |
| Tabelas, models e enum de agendamentos e anexos | Implementado (migrations, models, `StatusAgendamento`) |
| Criar agendamento (`POST /api/agendamentos`) | **Só no contrato OpenAPI**: sem rota/controller ainda |
| Download de anexo por URL assinada (`GET /api/anexos/{anexo}`) | **Só no contrato OpenAPI**: rota `anexos.show` ainda não existe |
| Rotas admin de agendamentos (`/api/admin/agendamentos...`) | **Só no contrato OpenAPI**: sem rota/controller ainda |

Os endpoints que só existem no contrato podem ser consumidos pelo front através do [mock com Prism](#documentação-openapi-e-mock-da-api) enquanto o back não os implementa.

## Stack

- PHP `^8.3`
- Laravel `^13.17`
- Laravel Sanctum `^4.0` (token Bearer)
- MySQL (padrão do `.env.example`)
- Pest `^5.3` para testes (SQLite em memória)
- Laravel Pint (formatação) e Laravel Pail (logs em tempo real), como dependências de desenvolvimento
- OpenAPI 3.0.3 em `docs/openapi.yaml`

## Requisitos

- PHP 8.3 ou superior, com as extensões usuais do Laravel (`mbstring`, `openssl`, `tokenizer`, `xml`, `fileinfo`, `pdo_mysql`). Para rodar os testes, também `pdo_sqlite`.
- [Composer](https://getcomposer.org/)
- MySQL rodando localmente
- Node.js (opcional): só é necessário para rodar o mock da API com `npx`

## Instalação passo a passo

```bash
# 1. Entre na pasta do projeto
cd car-wash-back

# 2. Instale as dependências PHP
composer install

# 3. Copie o arquivo de ambiente (veja a próxima seção)
cp .env.example .env

# 4. Gere a chave da aplicação (preenche APP_KEY no .env)
php artisan key:generate

# 5. Crie o banco no MySQL (o Laravel não cria o banco sozinho)
#    mysql -u root -p -e "CREATE DATABASE db_car_wash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 6. Edite o .env: DB_USERNAME, DB_PASSWORD, ADMIN_EMAIL e ADMIN_PASSWORD

# 7. Crie as tabelas
php artisan migrate

# 8. Popule os serviços e crie o usuário admin
php artisan db:seed

# 9. Suba o servidor
php artisan serve
```

A API fica em `http://localhost:8000`.

**Atalho:** `composer setup` executa `composer install`, copia o `.env` (se ainda não existir), gera a `APP_KEY` e roda `migrate --force`. Como ele roda as migrations logo em seguida, só funciona de primeira se o banco já existir e se as credenciais padrão do `.env.example` (usuário `root`, sem senha) estiverem corretas. Ele **não** roda o seeder, então rode `php artisan db:seed` depois.

## Como copiar o `.env`

O `.env` guarda as configurações locais e segredos, por isso **não é versionado** (está no `.gitignore`). O repositório traz o modelo `.env.example`; basta copiá-lo:

```bash
# Linux / macOS / Git Bash
cp .env.example .env

# Windows (cmd)
copy .env.example .env

# Windows (PowerShell)
Copy-Item .env.example .env
```

Depois, gere a chave da aplicação:

```bash
php artisan key:generate
```

Se você alterar o `.env` com o servidor rodando, reinicie o `php artisan serve`. Se a configuração estiver em cache, rode `php artisan config:clear`.

## Variáveis do `.env`

### Aplicação

| Variável | Valor no exemplo | Significado |
| --- | --- | --- |
| `APP_NAME` | `"Car Wash"` | Nome da aplicação. Também é usado como nome do remetente dos e-mails (`MAIL_FROM_NAME`). |
| `APP_ENV` | `local` | Ambiente de execução: `local`, `testing` ou `production`. |
| `APP_KEY` | *(vazio)* | Chave usada para criptografia e assinatura. Preencha com `php artisan key:generate`. Nunca compartilhe nem versione. |
| `APP_DEBUG` | `true` | Mostra detalhes de erro nas respostas. Use `false` em produção. |
| `APP_URL` | `http://localhost:8000` | URL base da API. É usada para montar URLs absolutas (como as URLs assinadas de anexos). Se mudar a porta do servidor, atualize aqui. |

### Idioma

| Variável | Valor no exemplo | Significado |
| --- | --- | --- |
| `APP_LOCALE` | `pt_BR` | Idioma das mensagens de validação e de erro (arquivos em `lang/pt_BR`). |
| `APP_FALLBACK_LOCALE` | `en` | Idioma usado quando falta uma tradução. |
| `APP_FAKER_LOCALE` | `pt_BR` | Idioma dos dados falsos gerados pelas factories. |

### Segurança

| Variável | Valor no exemplo | Significado |
| --- | --- | --- |
| `BCRYPT_ROUNDS` | `12` | Custo do hash de senhas. Quanto maior, mais lento e mais seguro. (Nos testes é `4`, definido no `phpunit.xml`.) |

### Logs

| Variável | Valor no exemplo | Significado |
| --- | --- | --- |
| `LOG_CHANNEL` | `stack` | Canal de log principal. |
| `LOG_STACK` | `single` | Canais dentro do `stack`. `single` grava tudo em `storage/logs/laravel.log`. |
| `LOG_LEVEL` | `debug` | Nível mínimo registrado (`debug`, `info`, `warning`, `error`...). |

### Banco de dados

| Variável | Valor no exemplo | Significado |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` | Driver do banco. |
| `DB_HOST` | `127.0.0.1` | Endereço do servidor MySQL. |
| `DB_PORT` | `3306` | Porta do MySQL. |
| `DB_DATABASE` | `db_car_wash` | Nome do banco. Ele precisa existir antes de rodar as migrations. |
| `DB_USERNAME` | `root` | Usuário do MySQL. Troque pelo seu. |
| `DB_PASSWORD` | *(vazio)* | Senha do MySQL. Troque pela sua. |

### Sessão, cache e fila

| Variável | Valor no exemplo | Significado |
| --- | --- | --- |
| `SESSION_DRIVER` | `file` | Onde as sessões ficam guardadas. A API usa token Bearer, então não depende de sessão. |
| `CACHE_STORE` | `file` | Onde o cache fica guardado (`storage/framework/cache`). O limitador de tentativas do login e o hash falso usado na verificação de senha usam esse cache. |
| `QUEUE_CONNECTION` | `sync` | Jobs rodam na hora, sem worker de fila. |

### CORS (integração com o front)

| Variável | Valor no exemplo | Significado |
| --- | --- | --- |
| `FRONTEND_URL` | `http://localhost:5173` | Origem do front liberada no CORS para as rotas `api/*`. Aceita várias origens separadas por vírgula, por exemplo `http://localhost:5173,https://meusite.com`. Deve ser a origem exata, sem barra no final. |

### Usuário admin (usado pelo seeder)

| Variável | Valor no exemplo | Significado |
| --- | --- | --- |
| `ADMIN_NAME` | `"Administrador"` | Nome do admin criado pelo `db:seed`. |
| `ADMIN_EMAIL` | *(vazio)* | E-mail de login do admin. **Obrigatório** para o seeder criar o admin. |
| `ADMIN_PASSWORD` | *(vazio)* | Senha do admin, com **no mínimo 12 caracteres**. **Obrigatória** para o seeder criar o admin. |

Comportamento do seeder com essas variáveis:

- Se `ADMIN_EMAIL` ou `ADMIN_PASSWORD` estiver vazio, mostra um aviso e **não cria** o admin.
- Se a senha tiver menos de 12 caracteres, mostra um erro e **não cria** o admin.
- Se já existir um usuário com esse e-mail, ele **não é alterado** (a senha não é atualizada).

### E-mail

| Variável | Valor no exemplo | Significado |
| --- | --- | --- |
| `MAIL_MAILER` | `log` | Com `log`, nenhum e-mail é enviado de verdade: o conteúdo é gravado no log. |
| `MAIL_FROM_ADDRESS` | `"contato@exemplo.com"` | Endereço do remetente. |
| `MAIL_FROM_NAME` | `"${APP_NAME}"` | Nome do remetente (reaproveita o `APP_NAME`). |

### Outras variáveis (opcionais)

Não estão no `.env.example`, mas as configs do projeto as aceitam:

| Variável | Padrão | Significado |
| --- | --- | --- |
| `FILESYSTEM_DISK` | `local` | Disco padrão de arquivos. Os anexos dos agendamentos são lidos e apagados no disco `local` (`storage/app/private`). |
| `SANCTUM_TOKEN_PREFIX` | *(vazio)* | Prefixo adicionado aos tokens gerados. |

> A validade do token é de **480 minutos (8 horas)** e está fixa em `config/sanctum.php`, não no `.env`.

## Banco de dados e migrations

Antes de tudo, crie o banco informado em `DB_DATABASE`:

```sql
CREATE DATABASE db_car_wash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Comandos úteis:

```bash
# Cria as tabelas que ainda não foram criadas
php artisan migrate

# Mostra quais migrations já rodaram
php artisan migrate:status

# Desfaz o último lote de migrations
php artisan migrate:rollback

# APAGA todas as tabelas e recria tudo (só em desenvolvimento!)
php artisan migrate:fresh

# Recria tudo e já roda os seeders
php artisan migrate:fresh --seed
```

Em produção, use `php artisan migrate --force`.

### Tabelas criadas

| Migration | Tabelas |
| --- | --- |
| `0001_01_01_000000_create_users_table` | `users`, `password_reset_tokens`, `sessions` |
| `0001_01_01_000001_create_cache_table` | `cache`, `cache_locks` |
| `0001_01_01_000002_create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` |
| `2026_10_11_192713_create_personal_access_tokens_table` | `personal_access_tokens` (tokens do Sanctum) |
| `2026_10_14_000001_create_servicos_table` | `servicos` (`nome` único, `descricao`, `ativo`) |
| `2026_10_14_000002_create_agendamentos_table` | `agendamentos` (`nome`, `telefone`, `endereco`, `servico_id`, `data`, `horario`, `status`, `observacoes`) |
| `2026_10_14_000003_create_agendamento_anexos_table` | `agendamento_anexos` (`agendamento_id`, `caminho`, `nome_original`, `mime_type`, `tamanho`) |

Regras de relacionamento:

- Um serviço não pode ser apagado se tiver agendamentos (`restrictOnDelete`).
- Apagar um agendamento apaga os registros de anexos (`cascadeOnDelete`) e também os arquivos do disco `local`.
- O `status` do agendamento nasce como `pendente` e aceita `pendente`, `confirmado`, `concluido` ou `cancelado` (enum `StatusAgendamento`).

## Seeders (serviços e admin)

```bash
php artisan db:seed
```

O `DatabaseSeeder` faz duas coisas:

1. **`ServicoSeeder`**: cadastra 8 serviços de lavagem (muros e paredes externas, portões e grades, fachadas e revestimentos, calçadas e pisos externos, pedras e revestimentos, garagens e áreas de circulação, áreas de lazer, áreas PET e espaços externos). Usa `firstOrCreate`, então pode ser executado várias vezes sem duplicar.
2. **Admin**: cria o usuário administrador a partir das variáveis `ADMIN_*` do `.env` (regras na seção [Usuário admin](#usuário-admin-usado-pelo-seeder)).

Para rodar só os serviços:

```bash
php artisan db:seed --class=ServicoSeeder
```

## Como rodar o servidor

```bash
php artisan serve
```

Por padrão sobe em `http://127.0.0.1:8000`. Alternativas:

```bash
# Outra porta (lembre de atualizar APP_URL no .env)
php artisan serve --port=8001

# Via Composer
composer dev
```

Para conferir se está no ar:

- `GET http://localhost:8000/up` — health check do Laravel.
- `GET http://localhost:8000/api/servicos` — lista os serviços.

A raiz `/` não tem página: ela responde `404` de propósito, já que o projeto é só API.

Para acompanhar os logs em tempo real durante o desenvolvimento:

```bash
php artisan pail
```

## Endpoints implementados

Todos os caminhos têm o prefixo `/api`.

| Método | Rota | Auth | Descrição |
| --- | --- | --- | --- |
| `GET` | `/api/servicos` | Não | Lista os serviços ativos (`id`, `nome`, `descricao`), ordenados por `id`. |
| `POST` | `/api/login` | Não | Autentica o admin e devolve um token. Limite por IP: 5 por minuto e 30 por hora. |
| `POST` | `/api/logout` | Bearer | Revoga o token atual. |

### Exemplos

```bash
# Listar serviços
curl http://localhost:8000/api/servicos

# Login (devolve {"token": "1|..."})
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@exemplo.com","password":"sua-senha-com-12-ou-mais"}'

# Logout
curl -X POST http://localhost:8000/api/logout \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Accept: application/json"
```

### Regras de autenticação

- O token vale por 8 horas.
- Cada novo login **revoga os tokens anteriores**: só o último login continua válido.
- E-mail inexistente e senha errada devolvem exatamente a mesma resposta (`422`, `"E-mail ou senha inválidos"`), para não revelar quais e-mails existem.

### Formato dos erros

Todos os erros são JSON, mesmo sem o header `Accept: application/json`.

| Status | Quando | Corpo |
| --- | --- | --- |
| `401` | Token ausente, inválido ou revogado | `{"message": "Não autenticado."}` |
| `403` | URL assinada inválida ou expirada | `{"message": "Link inválido ou expirado."}` |
| `404` | Rota ou recurso inexistente | `{"message": "Recurso não encontrado."}` |
| `405` | Método HTTP não permitido | `{"message": "Método não permitido."}` |
| `413` | Upload acima do limite do servidor | `{"message": "Os arquivos enviados excedem o tamanho máximo permitido."}` |
| `422` | Validação falhou | `{"message": "...", "errors": {"campo": ["..."]}}` |
| `429` | Muitas tentativas | `{"message": "Muitas tentativas. Tente novamente em instantes.", "retry_after": 42}` (e header `Retry-After`) |

## Documentação OpenAPI e mock da API

O contrato da API está em [`docs/openapi.yaml`](docs/openapi.yaml) (OpenAPI 3.0.3). Ele descreve os endpoints implementados **e também os que ainda serão implementados** (criação de agendamento, download de anexo e rotas admin de agendamentos). Os caminhos já incluem `/api`, então o front só troca o host entre o mock e o back real.

### Rodando o mock com Prism

Não precisa instalar nada além do Node.js. Na raiz do projeto:

```bash
npx @stoplight/prism-cli mock docs/openapi.yaml
```

O mock sobe em `http://localhost:4010` e responde com os exemplos do contrato:

```bash
curl http://localhost:4010/api/servicos
```

Para o front, basta apontar a URL base da API para `http://localhost:4010` durante o desenvolvimento e trocar para `http://localhost:8000` (ou a URL de produção) quando for usar o back real.

### Forçando cenários

O Prism aceita o header `Prefer` para escolher qual resposta devolver:

```bash
# Forçar um status específico (ex.: erro de validação)
curl -X POST http://localhost:4010/api/login \
  -H "Content-Type: application/json" \
  -H "Prefer: code=422" \
  -d '{"email":"a@b.com","password":"x"}'

# Forçar um exemplo nomeado (nomes definidos no YAML, ex.: "vazia", "pagina", "horarioPassado")
curl http://localhost:4010/api/servicos -H "Prefer: example=vazia"
```

Observações:

- As rotas protegidas (`/api/logout` e `/api/admin/...`) exigem o header `Authorization: Bearer <qualquer-valor>` no mock; sem ele o Prism responde `401`.
- O criar agendamento é `multipart/form-data`, com as fotos em `anexos[]`.

### Visualizando a documentação

Para ler o contrato em uma interface navegável, você pode abrir o conteúdo de `docs/openapi.yaml` no [Swagger Editor](https://editor.swagger.io/) ou usar, por exemplo:

```bash
npx @redocly/cli preview-docs docs/openapi.yaml
```

### O que já existe no back e o que é só contrato

| Endpoint | No back | No OpenAPI |
| --- | --- | --- |
| `GET /api/servicos` | Sim | Sim |
| `POST /api/login` | Sim | Sim |
| `POST /api/logout` | Sim | Sim |
| `POST /api/agendamentos` | Não | Sim |
| `GET /api/anexos/{anexo}` | Não | Sim |
| `GET /api/admin/agendamentos` | Não | Sim |
| `GET /api/admin/agendamentos/{agendamento}` | Não | Sim |
| `PATCH /api/admin/agendamentos/{agendamento}` | Não | Sim |

Quando o back implementar esses endpoints, o YAML é a referência de regras: campos obrigatórios, limites (até 5 fotos JPG/PNG/WebP de até 5 MB, data de hoje até 90 dias à frente), paginação de 20 itens e formato das respostas.

## Testes

Os testes usam **Pest** e rodam com **SQLite em memória**, então não tocam no seu banco MySQL (é preciso ter a extensão `pdo_sqlite` habilitada no PHP).

```bash
composer test

# ou, direto
php artisan test
```

Para rodar um arquivo ou filtrar por nome:

```bash
php artisan test tests/Feature/AuthAdminTest.php
php artisan test --filter="logout"
```

Cobertura atual (`tests/Feature`):

| Arquivo | O que testa |
| --- | --- |
| `AuthAdminTest.php` | Login, token único por usuário, logout, login com o admin criado pelo seeder |
| `ErrosECorsTest.php` | Formato de erros 401/404/422, rate limit do login (429) e CORS |
| `ServicosTest.php` | Listagem pública só com serviços ativos e lista vazia |
| `PaginaInicialTest.php` | A raiz `/` responde 404 |

Para padronizar o código com o Pint:

```bash
./vendor/bin/pint
```

## Estrutura do projeto

```
.
├── app/
│   ├── Enums/StatusAgendamento.php      # pendente | confirmado | concluido | cancelado
│   ├── Http/Controllers/                # AuthController, ServicoController
│   ├── Models/                          # User, Servico, Agendamento, AgendamentoAnexo
│   └── Providers/AppServiceProvider.php # rate limiter "login"
├── bootstrap/app.php                    # rotas, health check e respostas de erro em JSON
├── config/                              # admin.php, cors.php, sanctum.php, etc.
├── database/
│   ├── factories/                       # UserFactory
│   ├── migrations/
│   └── seeders/                         # DatabaseSeeder, ServicoSeeder
├── docs/openapi.yaml                    # contrato da API (usado também pelo mock)
├── lang/pt_BR/                          # traduções de validação, auth, etc.
├── routes/api.php                       # rotas da API
├── storage/                             # logs, cache e arquivos privados (anexos)
└── tests/                               # testes Pest
```

## Problemas comuns

| Sintoma | Causa provável | Solução |
| --- | --- | --- |
| `No application encryption key has been specified` | `APP_KEY` vazio | `php artisan key:generate` |
| `SQLSTATE[HY000] [1049] Unknown database` | O banco não existe | Crie o banco com o nome de `DB_DATABASE` |
| `SQLSTATE[HY000] [1045] Access denied` | Usuário ou senha do MySQL incorretos | Ajuste `DB_USERNAME` e `DB_PASSWORD` no `.env` |
| `ADMIN_EMAIL/ADMIN_PASSWORD não definidos` no seeder | Variáveis vazias | Preencha no `.env` e rode `php artisan db:seed` de novo |
| `ADMIN_PASSWORD precisa ter ao menos 12 caracteres` | Senha curta | Use uma senha com 12+ caracteres |
| Mudei a senha no `.env` mas o login antigo continua | O seeder não atualiza admin existente | Altere a senha pelo banco ou apague o usuário e rode o seeder de novo |
| Erro de CORS no navegador | `FRONTEND_URL` diferente da origem do front | Coloque a origem exata (protocolo, host e porta) em `FRONTEND_URL` |
| `429 Muitas tentativas` no login | Mais de 5 tentativas por minuto (ou 30 por hora) do mesmo IP | Aguarde o tempo do header `Retry-After` |
| Alterações no `.env` não surtem efeito | Configuração em cache | `php artisan config:clear` |
| Testes falham com `could not find driver` | Falta `pdo_sqlite` no PHP | Habilite a extensão `pdo_sqlite` no `php.ini` |

## Licença

Distribuído sob a licença **Apache-2.0**. Veja o arquivo [LICENSE](LICENSE).