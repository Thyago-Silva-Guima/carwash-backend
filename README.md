# Car Wash - Back-end

API REST para agendamento de serviços de lavagem de áreas externas (muros, portões, fachadas, calçadas, garagens e afins). Feita em **Laravel 13**, com autenticação por token Bearer via **Laravel Sanctum**. Todas as respostas são JSON, e as mensagens de validação estão em português (`pt_BR`).

O projeto reúne três módulos, de três responsáveis diferentes:

| Módulo | Cobre |
| --- | --- |
| Contas e cadastros | Login e cadastro de cliente, login da equipe, serviços, endereços, migrations e seeders |
| Disponibilidade e agendamento | Janelas de atendimento, horários livres, criação e cancelamento de agendamentos pelo cliente |
| Painel e Google Agenda | Pedidos da equipe (confirmar, recusar, concluir) e sincronização com o Google Agenda |

O histórico de integração e as decisões de cada módulo estão em [INTEGRACAO.md](INTEGRACAO.md). Este README descreve o estado atual do código.

## Sumário

- [Estado atual](#estado-atual)
- [Stack](#stack)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Configuração (`.env`)](#configuração-env)
- [Banco de dados](#banco-de-dados)
- [Seeders e usuários](#seeders-e-usuários)
- [Rodando o servidor](#rodando-o-servidor)
- [Autenticação e perfis](#autenticação-e-perfis)
- [Endpoints](#endpoints)
- [Formato das respostas e erros](#formato-das-respostas-e-erros)
- [Regras de negócio](#regras-de-negócio)
- [Google Agenda](#google-agenda)
- [Documentação da API e mock](#documentação-da-api-e-mock)
- [Testes e qualidade de código](#testes-e-qualidade-de-código)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Problemas comuns](#problemas-comuns)
- [Pendências conhecidas](#pendências-conhecidas)
- [Licença](#licença)

## Estado atual

| Área | Situação |
| --- | --- |
| Cadastro, login, logout e dados do cliente (`/api/auth/*`), incluindo edição de nome, e-mail, telefone e senha | Implementado e testado |
| Login, logout e dados da equipe (`/api/admin/*`) | Implementado e testado |
| Endereços do cliente (`/api/enderecos`) | Implementado e testado |
| Serviços ativos (`GET /api/servicos`) | Implementado e testado |
| Horários livres e dias com vaga (`/api/disponibilidade*`) | Implementado e testado |
| Criar, listar, consultar e cancelar agendamentos pelo cliente (`/api/agendamentos*`) | Implementado e testado |
| Painel da equipe: listar, consultar, confirmar, recusar e concluir pedidos (`/api/admin/agendamentos*`) | Implementado e testado |
| Janelas de atendimento e cadastro em lote (`/api/admin/disponibilidades*`) | Implementado e testado |
| Sincronização com o Google Agenda | Implementado. Só fica ativa quando há Google configurado (ver [Google Agenda](#google-agenda)) |
| Upload de fotos dos agendamentos e download por URL assinada | **Pendente**: a tabela e o model existem, mas não há rota nem controller |
| Redefinição de senha do cliente pela equipe | **Pendente** |

A lista completa de pendências está em [Pendências conhecidas](#pendências-conhecidas).

## Stack

- PHP `^8.3`
- Laravel `^13.17`
- Laravel Sanctum `^4.0` (autenticação por token Bearer)
- MySQL (banco de desenvolvimento e produção)
- Pest (testes), com SQLite em memória
- Laravel Pint (formatação), Laravel Pail (logs em tempo real) e Laravel Pao, como dependências de desenvolvimento
- OpenAPI 3.0.3 em `docs/openapi.yaml`

## Requisitos

- PHP 8.3 ou superior, com as extensões `mbstring`, `openssl`, `tokenizer`, `xml`, `fileinfo` e `pdo_mysql`. Para rodar os testes, também `pdo_sqlite`.
- Composer
- MySQL 8 (ou compatível) rodando localmente
- Node.js, só se você quiser o mock da API com o Prism

No Windows, o Laragon já traz PHP e MySQL. No Linux e no macOS, instale os pacotes equivalentes.

## Instalação

```bash
# 1. Entre na pasta do projeto
cd carwash-backend

# 2. Instale as dependências
composer install

# 3. Crie o arquivo de ambiente
cp .env.example .env            # Windows (cmd): copy .env.example .env

# 4. Gere a chave da aplicação (preenche APP_KEY no .env)
php artisan key:generate

# 5. Crie o banco no MySQL (o Laravel não cria o banco sozinho)
mysql -u root -p -e "CREATE DATABASE db_car_wash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 6. Edite o .env: DB_USERNAME, DB_PASSWORD, ADMIN_EMAIL e ADMIN_PASSWORD

# 7. Crie as tabelas
php artisan migrate

# 8. Cadastre os serviços e o usuário admin
php artisan db:seed

# 9. Suba o servidor
php artisan serve
```

A API fica em `http://localhost:8000`.

> **Atenção:** se você já tinha o banco de uma versão anterior do projeto, rode `php artisan migrate:fresh --seed` no lugar do passo 7 e do passo 8. Isso apaga todas as tabelas e os dados. Use só em desenvolvimento.

O script `composer setup` faz `composer install`, copia o `.env`, gera a `APP_KEY` e roda `migrate --force`. Ele **não** roda o seeder, então rode `php artisan db:seed` depois. Ele só funciona de primeira se o banco já existir e se as credenciais do `.env.example` (usuário `root`, sem senha) estiverem corretas para a sua máquina.

## Configuração (`.env`)

O `.env` guarda configurações locais e segredos, por isso **não é versionado**. O repositório traz o modelo `.env.example`.

Depois de alterar o `.env`, reinicie o `php artisan serve` e rode `php artisan config:clear`, se a configuração estiver em cache.

### Aplicação e idioma

| Variável | Exemplo | Significado |
| --- | --- | --- |
| `APP_NAME` | `"Car Wash"` | Nome da aplicação. Também é o nome do remetente dos e-mails. |
| `APP_ENV` | `local` | `local`, `testing` ou `production`. Em `local` e `testing`, sem Google configurado, o sistema não chama o Google. |
| `APP_KEY` | *(vazio)* | Chave de criptografia. Gere com `php artisan key:generate`. Nunca versione. |
| `APP_DEBUG` | `true` | Mostra detalhes de erro. Use `false` em produção. |
| `APP_URL` | `http://localhost:8000` | URL base da API. Atualize se mudar a porta do servidor. |
| `APP_LOCALE` | `pt_BR` | Idioma das mensagens de validação (`lang/pt_BR`). |
| `APP_FALLBACK_LOCALE` | `en` | Idioma usado quando falta uma tradução. |
| `APP_FAKER_LOCALE` | `pt_BR` | Idioma dos dados falsos das factories. |

O fuso horário do app é `America/Sao_Paulo` (fixo em `config/app.php`). Os agendamentos são guardados nesse fuso.

### Banco de dados

| Variável | Exemplo | Significado |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` | Driver do banco. |
| `DB_HOST` | `127.0.0.1` | Servidor MySQL. |
| `DB_PORT` | `3306` | Porta do MySQL. |
| `DB_DATABASE` | `db_car_wash` | Nome do banco. Precisa existir antes das migrations. |
| `DB_USERNAME` | `root` | Usuário do MySQL. |
| `DB_PASSWORD` | *(vazio)* | Senha do MySQL. |

### Segurança

| Variável | Exemplo | Significado |
| --- | --- | --- |
| `BCRYPT_ROUNDS` | `12` | Custo do hash de senhas. Nos testes é `4`, definido no `phpunit.xml`. |

O token da API expira em **480 minutos (8 horas)**. O valor fica em `config/sanctum.php`. `SANCTUM_TOKEN_PREFIX` (opcional) adiciona um prefixo aos tokens.

### Sessão, cache e fila

| Variável | Exemplo | Significado |
| --- | --- | --- |
| `SESSION_DRIVER` | `file` | A API usa token, então não depende de sessão. |
| `CACHE_STORE` | `file` | Usado pelo limitador de tentativas e pelo hash de verificação de senha. |
| `QUEUE_CONNECTION` | `sync` | Jobs e listeners rodam na hora, sem worker. |

Se você trocar `QUEUE_CONNECTION` para `database`, o projeto ainda não tem a migration da tabela `jobs`. Crie-a antes com `php artisan queue:table`.

### Logs

| Variável | Exemplo | Significado |
| --- | --- | --- |
| `LOG_CHANNEL` | `stack` | Canal principal. |
| `LOG_STACK` | `single` | Grava em `storage/logs/laravel.log`. |
| `LOG_LEVEL` | `debug` | Nível mínimo registrado. |

### Agendamento

Valores provisórios: o cliente final ainda vai definir os reais.

| Variável | Exemplo | Significado |
| --- | --- | --- |
| `AGENDA_PASSO` | `30` | Intervalo, em minutos, entre um horário e o seguinte. |
| `AGENDA_ANTEC_AGENDAR_H` | `2` | Antecedência mínima, em horas, para agendar. |
| `AGENDA_ANTEC_CANCELAR_H` | `2` | Antecedência mínima, em horas, para o cliente cancelar. |
| `AGENDA_LIMITE_DIAS` | `90` | Quantos dias à frente o cliente pode agendar. |

### Google Agenda

| Variável | Exemplo | Significado |
| --- | --- | --- |
| `GOOGLE_CALENDAR_ID` | *(vazio)* | ID da agenda do Google que recebe os agendamentos. |
| `GOOGLE_APPLICATION_CREDENTIALS` | *(vazio)* | Caminho do arquivo JSON da conta de serviço do Google. |

Os detalhes de funcionamento estão em [Google Agenda](#google-agenda).

### CORS (integração com o front)

| Variável | Exemplo | Significado |
| --- | --- | --- |
| `FRONTEND_URL` | `http://localhost:5173` | Origem do front liberada para `api/*`. Aceita várias origens separadas por vírgula, sem barra no final. |

### Admin (usado pelo seeder)

| Variável | Significado |
| --- | --- |
| `ADMIN_NAME` | Nome do admin criado pelo `db:seed`. |
| `ADMIN_EMAIL` | E-mail de login do admin. Obrigatório para o seeder criar o admin. |
| `ADMIN_PASSWORD` | Senha do admin, com **no mínimo 12 caracteres**. Obrigatória para o seeder criar o admin. |

### E-mail

| Variável | Exemplo | Significado |
| --- | --- | --- |
| `MAIL_MAILER` | `log` | Com `log`, nenhum e-mail é enviado: o conteúdo vai para o log. |
| `MAIL_FROM_ADDRESS` | `"contato@exemplo.com"` | Remetente. |
| `MAIL_FROM_NAME` | `"${APP_NAME}"` | Nome do remetente. |

## Banco de dados

O banco é o `DB_DATABASE`. As migrations ficam em `database/migrations` e criam estas tabelas:

| Domínio | Tabelas | Uso |
| --- | --- | --- |
| Contas | `usuario` | Equipe: administrador (`perfil = admin`) e funcionário (`perfil = funcionario`). |
| Contas | `cliente` | Clientes, com `tipo` (padrão `residencial`) e `aceite_termos_em`. |
| Contas | `endereco` | Endereços do cliente. Ligados ao cliente. |
| Catálogo | `servico` | Serviços, com `categoria`, `duracao_minutos`, `preco_base` e `ativo`. |
| Agenda | `disponibilidade` | Janelas de atendimento: data, horário inicial e final, capacidade e quem cadastrou. |
| Agenda | `agendamento` | Pedidos: cliente, endereço, período (`data_hora` a `data_hora_fim`), `status`, `valor_total`, `motivo` e `google_event_id`. |
| Agenda | `agendamento_servico` | Serviços de cada agendamento, com `quantidade` e `preco_unitario`. |
| Anexos | `anexo_agendamento` | Metadados de arquivos de agendamento. Ainda sem rota de upload. |
| Tokens | `personal_access_tokens` | Tokens do Sanctum. |
| Reservadas | `pacote`, `pacote_servico`, `plano_manutencao`, `foto` | Criadas, mas ainda sem model nem rota. |

Regras de integridade relevantes:

- Não é possível apagar um serviço que já tem agendamentos.
- Apagar um agendamento apaga também os registros de anexos e os arquivos correspondentes no disco `local`.
- O `status` do agendamento só aceita `pendente`, `confirmado`, `concluido` ou `cancelado`. Isso é garantido também por uma constraint no banco.

Comandos úteis:

```bash
php artisan migrate                  # cria as tabelas que faltam
php artisan migrate:status           # mostra o que já rodou
php artisan migrate:rollback         # desfaz o último lote
php artisan migrate:fresh --seed     # APAGA tudo e recria (só em desenvolvimento)
php artisan migrate --force          # em produção
```

## Seeders e usuários

```bash
php artisan db:seed                              # serviços + admin
php artisan db:seed --class=ServicoSeeder        # só os serviços
php artisan db:seed --class=DevFuncionarioSeeder # funcionário de teste (não roda em produção)
```

- **`ServicoSeeder`** cadastra os 8 serviços iniciais de lavagem (muros e paredes externas, portões e grades, fachadas e revestimentos, calçadas e pisos externos, pedras e revestimentos, garagens e áreas de circulação, áreas de lazer e áreas PET). Ele não duplica se rodar de novo.
- **`DatabaseSeeder`** cria o administrador a partir de `ADMIN_NAME`, `ADMIN_EMAIL` e `ADMIN_PASSWORD`:
  - Se e-mail ou senha estiverem vazios, avisa e **não cria** o admin.
  - Se a senha tiver menos de 12 caracteres, mostra erro e **não cria** o admin.
  - Se já existir um usuário com esse e-mail, ele **não é alterado**. A senha não é atualizada.
- **`DevFuncionarioSeeder`** cria `funcionario@teste.local` com perfil `funcionario`, para testar o acesso da equipe. Não roda em produção.

## Rodando o servidor

```bash
php artisan serve                  # http://127.0.0.1:8000
php artisan serve --port=8001      # outra porta (atualize APP_URL)
composer dev                       # mesmo que artisan serve, sem limite de tempo
php artisan pail                   # logs em tempo real
```

Para conferir se está no ar:

- `GET /up`: health check do Laravel.
- `GET /api/servicos`: lista os serviços ativos.

A raiz `/` responde 404 de propósito: o projeto é só API.

## Autenticação e perfis

A autenticação usa token Bearer do Sanctum. Envie `Authorization: Bearer <token>` nas rotas protegidas.

Há três perfis, e cada token só funciona nas rotas do seu perfil:

| Perfil | Como entra | Acesso |
| --- | --- | --- |
| Cliente | `POST /api/auth/register` ou `POST /api/auth/login` | Rotas `/api/auth/*` (logout, me), endereços, disponibilidade e agendamentos do próprio cliente |
| Funcionário | `POST /api/admin/login` (conta com `perfil = funcionario`) | Painel de pedidos: `/api/admin/agendamentos*` |
| Administrador | `POST /api/admin/login` (conta com `perfil = admin`) | Tudo do funcionário, mais as janelas de atendimento: `/api/admin/disponibilidades*` |

Regras:

- O token vale por 8 horas.
- Cada novo login **revoga os tokens anteriores**: só o último login continua válido.
- Token de tipo errado responde `403 {"message": "Acesso negado."}`. Sem token ou com token inválido, responde `401`.
- E-mail inexistente e senha errada respondem igual, para não revelar quais e-mails existem.
- Limite de tentativas por IP: login (cliente e equipe, compartilhado) de 5 por minuto e 30 por hora; cadastro de 10 por hora.
- Trocar o e-mail ou a senha do cliente encerra as outras sessões dele. A sessão que fez a alteração continua válida. A troca não exige a senha atual.

## Endpoints

Todos os caminhos têm o prefixo `/api`. A documentação completa, com corpos de requisição, exemplos e erros, está em [`docs/openapi.yaml`](docs/openapi.yaml).

### Público

| Método | Rota | Descrição |
| --- | --- | --- |
| `GET` | `/servicos` | Serviços ativos, ordenados por `id`. |
| `POST` | `/auth/register` | Cadastra um cliente e já devolve o token. |
| `POST` | `/auth/login` | Login do cliente. |
| `POST` | `/admin/login` | Login da equipe. |

### Cliente (token de cliente)

| Método | Rota | Descrição |
| --- | --- | --- |
| `POST` | `/auth/logout` | Revoga o token atual. |
| `GET` | `/auth/me` | Dados do cliente logado. |
| `PUT` | `/auth/me` | Edita nome, e-mail, telefone e senha. Envie só os campos que mudam. |
| `GET` | `/enderecos` | Lista os endereços do cliente. |
| `POST` | `/enderecos` | Cadastra um endereço. |
| `GET` | `/enderecos/{id}` | Mostra um endereço. |
| `PUT` | `/enderecos/{id}` | Substitui os dados de um endereço. |
| `DELETE` | `/enderecos/{id}` | Remove um endereço (não remove se já foi usado em agendamento). |
| `GET` | `/disponibilidade/dias?mes=AAAA-MM&servico_id=` | Dias do mês com pelo menos um horário livre. |
| `GET` | `/disponibilidade?data=AAAA-MM-DD&servico_id=` | Horários livres de um dia para o serviço. |
| `GET` | `/agendamentos` | Agendamentos do cliente logado, mais recentes primeiro. |
| `POST` | `/agendamentos` | Cria um agendamento pendente. |
| `GET` | `/agendamentos/{id}` | Mostra um agendamento do cliente. |
| `PATCH` | `/agendamentos/{id}/cancelar` | Cancela, respeitando a antecedência mínima. |

### Equipe (token de administrador ou funcionário)

| Método | Rota | Descrição |
| --- | --- | --- |
| `POST` | `/admin/logout` | Revoga o token atual. |
| `GET` | `/admin/me` | Dados do usuário logado. |
| `GET` | `/admin/agendamentos/resumo` | Quantidade de pedidos pendentes. |
| `GET` | `/admin/agendamentos` | Lista paginada, com filtros (ver abaixo). |
| `GET` | `/admin/agendamentos/{id}` | Detalhe do pedido, com cliente, endereço, serviços e atendente. |
| `PATCH` | `/admin/agendamentos/{id}/confirmar` | Confirma um pedido pendente. |
| `PATCH` | `/admin/agendamentos/{id}/recusar` | Recusa um pedido pendente. Exige `motivo`. |
| `PATCH` | `/admin/agendamentos/{id}/concluir` | Marca um pedido confirmado como concluído. |

Filtros da listagem `GET /admin/agendamentos`: `status`, `data_inicio`, `data_fim`, `cliente_id`, `servico_id`, `usuario_id` (atendente) e `per_page` (de 1 a 100, padrão 15).

### Administrador (token de administrador)

| Método | Rota | Descrição |
| --- | --- | --- |
| `GET` | `/admin/disponibilidades` | Lista as janelas, com filtros `data_inicio` e `data_fim`. |
| `POST` | `/admin/disponibilidades` | Cria uma janela de atendimento. |
| `GET` | `/admin/disponibilidades/{id}` | Mostra uma janela. |
| `PUT` / `PATCH` | `/admin/disponibilidades/{id}` | Altera horários, capacidade ou observação. |
| `DELETE` | `/admin/disponibilidades/{id}` | Remove uma janela. |
| `POST` | `/admin/disponibilidades/lote` | Cria janelas em lote, nos dias da semana escolhidos. |

### Exemplos

```bash
# Listar serviços (público)
curl http://localhost:8000/api/servicos

# Cadastrar cliente
curl -X POST http://localhost:8000/api/auth/register \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"nome":"Maria Souza","telefone":"(41) 99999-9999","email":"maria@exemplo.com","password":"senha-segura-123","aceite_termos":true}'

# Login da equipe
curl -X POST http://localhost:8000/api/admin/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"admin@seudominio.com","password":"sua-senha-com-12-ou-mais"}'

# Horários livres de um dia (cliente)
curl "http://localhost:8000/api/disponibilidade?data=2026-11-14&servico_id=1" \
  -H "Accept: application/json" -H "Authorization: Bearer SEU_TOKEN"

# Criar agendamento (cliente)
curl -X POST http://localhost:8000/api/agendamentos \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{"endereco_id":1,"data_hora":"2026-11-14T08:00:00-03:00","servicos":[{"servico_id":1,"quantidade":1}],"observacoes":"Muro com muito limo."}'
```

## Formato das respostas e erros

O formato depende do módulo:

- **Cadastro, login, dados do cliente, endereços e serviços** devolvem o objeto (ou a lista) diretamente.
- **Disponibilidade, agendamentos, painel e janelas** devolvem `{"data": ...}`.
- **A listagem paginada do painel** (`GET /api/admin/agendamentos`) devolve o paginador do Laravel, com `data` (a página), `current_page`, `last_page`, `total` e os links.

Todos os erros são JSON, mesmo sem `Accept: application/json`:

| Status | Quando | Corpo |
| --- | --- | --- |
| `401` | Token ausente, inválido ou revogado | `{"message": "Não autenticado."}` |
| `403` | Token de outro perfil, ou link assinado inválido | `{"message": "Acesso negado."}` |
| `404` | Rota inexistente, ou recurso que não pertence ao usuário | `{"message": "Recurso não encontrado."}` |
| `405` | Método HTTP não permitido | `{"message": "Método não permitido."}` |
| `409` | Ação não permitida no estado atual do pedido ou da janela | `{"message": "..."}` |
| `413` | Upload acima do limite do servidor | `{"message": "Os arquivos enviados excedem o tamanho máximo permitido."}` |
| `422` | Validação falhou | `{"message": "...", "errors": {"campo": ["..."]}}` |
| `429` | Muitas tentativas | `{"message": "Muitas tentativas. Tente novamente em instantes.", "retry_after": 42}`, com o header `Retry-After` |
| `503` | Agenda externa (Google) não pôde ser consultada ao calcular disponibilidade | `{"message": "Não foi possível consultar a agenda externa."}` |

O `message` de um `422` é a primeira mensagem de validação. Os detalhes de cada campo estão em `errors`.

## Regras de negócio

### Agendamento

- O pedido nasce `pendente`. No front, esse status deve aparecer como "Avaliando".
- O horário precisa cair em uma janela de atendimento, no intervalo de `AGENDA_PASSO` minutos, e caber inteiro na janela.
- Um horário só fica livre se a capacidade da janela não estiver esgotada e se não houver conflito com agendamentos `pendente` ou `confirmado`, nem com a agenda externa do Google.
- Antecedência mínima de `AGENDA_ANTEC_AGENDAR_H` horas para agendar. Não é possível agendar mais de `AGENDA_LIMITE_DIAS` dias à frente.
- Preço e duração vêm **sempre do banco**. O `valor_total` é a soma de `preco_base × quantidade`. Valores enviados pelo front são ignorados.
- Um mesmo serviço não pode aparecer duas vezes no pedido. A quantidade vai de 1 a 99.
- `observacoes` aceita até 500 caracteres.
- O endereço precisa pertencer ao cliente logado.

### Status do agendamento

| De | Para | Quem | Como |
| --- | --- | --- | --- |
| `pendente` | `confirmado` | Equipe | `PATCH .../confirmar` |
| `pendente` | `cancelado` | Equipe | `PATCH .../recusar`, com `motivo` obrigatório |
| `pendente` ou `confirmado` | `cancelado` | Cliente | `PATCH .../cancelar`, com antecedência de `AGENDA_ANTEC_CANCELAR_H` horas |
| `confirmado` | `concluido` | Equipe | `PATCH .../concluir` |

Ações fora dessas transições respondem `409`. Por exemplo, recusar um pedido já confirmado. Um pedido cancelado ou concluído não pode ser cancelado de novo.

Não há expiração automática de pedidos pendentes.

### Janelas de atendimento

- A data precisa ser hoje ou futura.
- `hora_fim` precisa ser depois de `hora_inicio`.
- A capacidade vai de 1 a 20 (padrão 1).
- Janelas do mesmo dia não podem se sobrepor.
- Alterar horário ou capacidade de uma janela que deixaria agendamentos de fora responde `409`, com os ids dos agendamentos afetados. Nada é alterado.
- Apagar uma janela que tem agendamentos responde `409`, com os ids afetados.
- A data de uma janela não pode ser alterada. Para outro dia, crie outra janela.
- No cadastro em lote, `dias_semana` usa `0` para domingo até `6` para sábado. Janelas que conflitam com as existentes são ignoradas e listadas em `ignoradas`.

## Google Agenda

Quando há Google configurado, a agenda funciona assim:

- Ao **confirmar** um pedido, cria-se um evento na agenda configurada.
- Ao **alterar** horário, data ou observação de um pedido já confirmado, o evento é atualizado.
- Ao **cancelar ou recusar**, o evento é removido.
- A disponibilidade considera os compromissos da agenda externa, além dos agendamentos do sistema.

Para ativar, preencha `GOOGLE_CALENDAR_ID` e `GOOGLE_APPLICATION_CREDENTIALS` no `.env`. O arquivo de credenciais é de uma conta de serviço do Google, com acesso de edição à agenda.

Comportamento por ambiente:

- **`local` ou `testing` sem `GOOGLE_CALENDAR_ID`**: a consulta de disponibilidade usa uma agenda vazia e **não chama o Google**.
- **`production` sem Google configurado**: a consulta de disponibilidade responde `503`. É o comportamento esperado, para não aceitar agendamentos sem conferir a agenda externa.

Os eventos (criar, atualizar e remover) são tratados por listeners que implementam `ShouldQueue`, com até 5 tentativas. Como isso se comporta depende da fila:

- Com `QUEUE_CONNECTION=sync` (o padrão do `.env.example`), o listener roda na hora, dentro da requisição. Uma falha do Google aparece como erro na confirmação do pedido.
- Com um worker de fila de verdade, a falha é reprocessada e não derruba a confirmação.

## Documentação da API e mock

O contrato da API está em [`docs/openapi.yaml`](docs/openapi.yaml) (OpenAPI 3.0.3). Ele descreve as 24 rotas implementadas, com regras de validação, erros e respostas. Os caminhos já incluem `/api`, então o front só troca o host entre o mock e o back.

O arquivo `docs/openapi_agendamento.yaml` é uma versão anterior e está obsoleto. Use só o `openapi.yaml`.

Para ver a documentação em uma interface navegável, cole o conteúdo de `docs/openapi.yaml` no [Swagger Editor](https://editor.swagger.io/).

### Mock com Prism

Na raiz do projeto:

```bash
npx @stoplight/prism-cli mock docs/openapi.yaml
```

O mock sobe em `http://localhost:4010` e responde com os exemplos do contrato:

```bash
curl http://localhost:4010/api/servicos
```

Para forçar um cenário, use o header `Prefer`:

```bash
# Forçar um status (ex.: erro de validação)
curl -X POST http://localhost:4010/api/auth/login \
  -H "Content-Type: application/json" -H "Prefer: code=422" \
  -d '{"email":"a@b.com","password":"x"}'

# Forçar um exemplo nomeado definido no YAML
curl http://localhost:4010/api/servicos -H "Prefer: example=vazia"
```

As rotas protegidas exigem `Authorization: Bearer <qualquer-valor>` no mock. Sem o header, o Prism responde `401`.

O mock valida o formato, mas não aplica as regras de negócio. Quem decide a regra real é o back e os testes.

### Coleção do Postman (QA)

Para testar o fluxo completo manualmente, importe `docs/postman/CarWash_QA.postman_collection.json` no Postman, usando o Desktop Agent. Antes:

1. Rode `php artisan db:seed --class=DevFuncionarioSeeder`.
2. Preencha as variáveis `adminEmail` e `adminSenha` da coleção com `ADMIN_EMAIL` e `ADMIN_PASSWORD` do `.env`.

Depois, rode as requisições na ordem.

## Testes e qualidade de código

Os testes usam **Pest** e rodam com **SQLite em memória**. Eles não tocam no seu MySQL, mas exigem a extensão `pdo_sqlite`.

```bash
composer test                  # limpa o cache de configuração e roda os testes
php artisan test               # só os testes

php artisan test tests/Feature/EdicaoPerfilClienteTest.php
php artisan test --filter="cancelar"
```

Os testes ficam em `tests/Feature` e `tests/Unit`. Eles cobrem:

| Arquivo | O que verifica |
| --- | --- |
| `AuthClienteTest`, `AuthAdminTest` | Cadastro, login, logout, token único e login com o admin do seeder |
| `EdicaoPerfilClienteTest` | Edição de dados do cliente e revogação de sessões |
| `EnderecosTest` | CRUD de endereços e isolamento entre clientes |
| `PerfisTest` | Um token não acessa as rotas do outro perfil |
| `ServicosTest`, `PaginaInicialTest` | Lista pública de serviços e a raiz `/` |
| `ErrosECorsTest` | Formato de erros, rate limit e CORS |
| `JanelasTest`, `DisponibilidadeTest`, `CalculoHorariosTest` | Janelas, horários livres e cálculo de slots |
| `AgendamentoTest`, `CancelamentoTest` | Criação, regras de antecedência e cancelamento |
| `ConcorrenciaTest` | Dois clientes disputando o mesmo horário |
| `FluxoIntegradoTest` | Cliente agenda, equipe confirma ou recusa |
| `GoogleCalendarServiceTest` | Integração com o Google (usa chave temporária gerada no teste) |

Formatação do código:

```bash
./vendor/bin/pint
```

## Estrutura do projeto

```
.
├── app/
│   ├── Contracts/AgendaExterna.php         # interface da agenda externa (Google ou vazia)
│   ├── Events/                             # AgendamentoConfirmado, Atualizado, Cancelado
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ClienteAuthController.php   # cadastro, login e dados do cliente
│   │   │   ├── AdminAuthController.php     # login da equipe
│   │   │   ├── EnderecoController.php
│   │   │   ├── ServicoController.php
│   │   │   ├── Disponibilidade/            # horários livres, janelas e lote
│   │   │   └── Agendamento/                # agendamento do cliente, cancelamento e painel
│   │   ├── Middleware/ExigePerfil.php      # cliente, admin, equipe
│   │   └── Requests/                       # validação de cada rota
│   ├── Listeners/                          # criam, atualizam e removem eventos do Google
│   ├── Models/                             # Cliente, User, Endereco, Servico, Agendamento, Disponibilidade...
│   ├── Providers/AppServiceProvider.php    # rate limits, listeners e agenda externa
│   └── Services/Agendamento/               # cálculo de horários, regras de agendamento, integração com o Google
├── bootstrap/app.php                       # rotas, health check e erros em JSON
├── config/                                 # admin, agendamento, cors, google_calendar, sanctum...
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/                            # DatabaseSeeder, ServicoSeeder, DevFuncionarioSeeder
├── docs/
│   ├── openapi.yaml                        # contrato da API
│   ├── postman/                            # coleção de QA
│   └── modulos/                            # documentação de cada módulo (dev-b, dev-c)
├── lang/pt_BR/                             # mensagens de validação em português
├── routes/
│   ├── api.php                             # contas, endereços e rotas da equipe
│   └── agendamento.php                     # disponibilidade, agendamentos e painel
├── storage/                                # logs, cache e arquivos privados
└── tests/                                  # testes Pest
```

## Problemas comuns

| Sintoma | Causa provável | Solução |
| --- | --- | --- |
| `No application encryption key has been specified` | `APP_KEY` vazio | `php artisan key:generate` |
| `SQLSTATE[HY000] [1049] Unknown database` | O banco não existe | Crie o banco com o nome de `DB_DATABASE` |
| `SQLSTATE[HY000] [1045] Access denied` | Usuário ou senha do MySQL incorretos | Ajuste `DB_USERNAME` e `DB_PASSWORD` |
| `Base table not found` | As migrations não rodaram | `php artisan migrate` (ou `migrate:fresh --seed`, se o banco for antigo) |
| `ADMIN_EMAIL/ADMIN_PASSWORD não definidos` no seeder | Variáveis vazias | Preencha no `.env` e rode `php artisan db:seed` de novo |
| `ADMIN_PASSWORD precisa ter ao menos 12 caracteres` | Senha curta | Use 12 caracteres ou mais |
| Mudei a senha do admin no `.env` e o login antigo continua | O seeder não atualiza um admin já existente | Altere a senha no banco, ou apague o usuário e rode o seeder de novo |
| Erro de CORS no navegador | `FRONTEND_URL` diferente da origem do front | Coloque a origem exata (protocolo, host e porta) |
| `429 Muitas tentativas` no login | Mais de 5 tentativas por minuto, ou 30 por hora, do mesmo IP | Aguarde o tempo de `Retry-After` |
| `409` ao cancelar ou recusar | O pedido já está em outro status | Confira o status antes de agir (ver [Status do agendamento](#status-do-agendamento)) |
| `422` com `agendamento` ou `data_hora` na antecedência | Pedido com menos de 2 horas de antecedência | Escolha outro horário |
| Alterações no `.env` não surtem efeito | Configuração em cache | `php artisan config:clear` |
| `could not find driver` nos testes | Falta `pdo_sqlite` | Habilite a extensão no `php.ini` |
| `503` ao consultar disponibilidade em produção | Agenda do Google não acessível | Confira `GOOGLE_CALENDAR_ID` e as credenciais |

## Pendências conhecidas

- **Fotos dos agendamentos:** upload, listagem e download por URL assinada. A tabela `anexo_agendamento` e o model `AgendamentoAnexo` existem, mas não há rota nem controller. O contrato em `docs/openapi.yaml` não inclui essas rotas.
- **Redefinição de senha do cliente** pela equipe.
- **Ajuste do valor ao confirmar** o pedido. Hoje o `valor_total` é só a soma da tabela de preços.
- **Capacidade de janela:** impedir reduzir a capacidade abaixo do número de agendamentos já existentes. Hoje a alteração de horário é validada, mas a capacidade ainda não.
- **`google_event_id` aparece nas respostas** de agendamento, inclusive as do cliente. É um identificador interno e deveria ficar oculto.
- **Listeners do Google em local ou testes sem `GOOGLE_CALENDAR_ID`:** o provider só registra os listeners quando há Google configurado, mas confirmar um pedido nesse cenário ainda pode chamá-los e responder `500`. O `FluxoIntegradoTest` mostrou esse caso. A causa não foi confirmada; o provider deveria pular os listeners nesse ambiente.
- **Concorrência real:** o `ConcorrenciaTest` envia os pedidos em sequência. Pedidos simultâneos precisam de teste manual ou de um teste com processos paralelos.
- **Reconciliação com o Google:** comando para detectar e corrigir eventos divergentes. Opcional, ainda não existe.
- **Tabelas reservadas** (`pacote`, `pacote_servico`, `plano_manutencao`, `foto`): sem model nem rota.

## Licença

Distribuído sob a licença **Apache-2.0**. Veja [LICENSE](LICENSE).