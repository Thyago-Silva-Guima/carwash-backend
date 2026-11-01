# CarWash Backend — Entrega das alterações Dev C

## Estrutura das alterações

```text
carwash-backend-master/
├── .env.example
├── app/
│   ├── Events/
│   │   └── AgendamentoAtualizado.php                         [novo]
│   ├── Http/
│   │   └── Controllers/
│   │       └── Agendamento/
│   │           └── AdminAgendamentoController.php            [novo]
│   ├── Listeners/
│   │   ├── AtualizarEventoGoogle.php                         [novo]
│   │   ├── CriarEventoGoogle.php                             [novo]
│   │   └── RemoverEventoGoogle.php                           [novo]
│   ├── Models/
│   │   └── Agendamento.php                                   [alterado]
│   ├── Providers/
│   │   └── AppServiceProvider.php                            [alterado]
│   └── Services/
│       └── Agendamento/
│           ├── DisponibilidadeService.php                    [alterado]
│           ├── GoogleCalendarAgenda.php                      [novo]
│           ├── GoogleCalendarException.php                   [novo]
│           └── GoogleCalendarService.php                     [novo]
├── config/
│   └── google_calendar.php                                   [novo]
├── docs/
│   ├── openapi_Thyago.yaml                                   [alterado]
│   └── README_Thyago.md                                      [alterado]
├── routes/
│   └── disponibilidade.php                                   [alterado]
└── tests/
    └── Unit/
        └── GoogleCalendarServiceTest.php                     [novo]
```

## O que foi implementado

### 1. Painel administrativo de agendamentos

Arquivo principal: `app/Http/Controllers/Agendamento/AdminAgendamentoController.php`

- Listagem com filtros opcionais por status, datas, cliente, serviço e atendente.
- Paginação Laravel; `per_page` aceita de 1 a 100 registros e usa 15 por padrão.
- Resumo com a contagem atual de agendamentos pendentes.
- Detalhes com as relações existentes: cliente, endereço, serviços e atendente.
- Confirmação apenas de agendamento pendente; associa o usuário autenticado como
  atendente e dispara `AgendamentoConfirmado`.
- Recusa apenas de agendamento pendente; exige e persiste o motivo e dispara
  `AgendamentoCancelado`.
- Conclusão apenas de agendamento confirmado.
- Alterações de estado com transação e lock da linha do agendamento para
  reduzir condições de corrida.
- Transições incompatíveis retornam HTTP 409.
- Não recebe valor do frontend nem recalcula o preço na confirmação: o código
  existente do Dev B calcula e persiste o preço na criação, e não há regra
  aprovada neste projeto para recalculá-lo depois.

Rotas adicionadas em `routes/disponibilidade.php`:

```text
GET   /api/admin/agendamentos
GET   /api/admin/agendamentos/resumo
GET   /api/admin/agendamentos/{id}
PATCH /api/admin/agendamentos/{id}/confirmar
PATCH /api/admin/agendamentos/{id}/recusar
PATCH /api/admin/agendamentos/{id}/concluir
```

As rotas usam os middlewares administrativos já disponíveis:
`auth.stub:usuario` e `admin`. O stub de autenticação permanece destinado
somente ao ambiente local; integrar Sanctum em produção depende da configuração
compartilhada com o Dev A.

### 2. Integração com Google Calendar

Arquivos principais:

- `app/Services/Agendamento/GoogleCalendarService.php`
- `app/Services/Agendamento/GoogleCalendarAgenda.php`
- `app/Services/Agendamento/GoogleCalendarException.php`
- `config/google_calendar.php`

O serviço implementa:

- Autenticação de service account com JWT assinado e troca por access token.
- Consulta free/busy da agenda configurada.
- Criação, atualização e remoção de eventos.
- Identificador determinístico para criação idempotente.
- Tratamento de conflito de criação (HTTP 409) como tentativa de atualizar o
  evento existente.
- Tratamento de evento ausente (HTTP 404) na atualização, recriando o evento;
  remoção de evento ausente é tratada como operação já concluída.
- Timeout HTTP e erros com status explícito, sem incluir token ou chave nos
  logs.

`GoogleCalendarAgenda` adapta o serviço à interface compartilhada
`App\Contracts\AgendaExterna`, sem alterar sua assinatura.

O binding do contrato agora usa o adaptador Google em
`app/Providers/AppServiceProvider.php`. O stub vazio
`AgendaExternaVazia` permanece no repositório, mas deixa de ser a implementação
registrada.

Quando a consulta externa falha, `DisponibilidadeService` registra apenas a
classe da exceção e retorna HTTP 503 pela aplicação. A falha não é interpretada
como ausência de compromissos nem como horário livre.

### 3. Eventos e processamento em fila

Listeners adicionados:

- `app/Listeners/CriarEventoGoogle.php`: cria evento para agendamento confirmado.
- `app/Listeners/AtualizarEventoGoogle.php`: sincroniza evento após alteração
  relevante em agendamento confirmado.
- `app/Listeners/RemoverEventoGoogle.php`: remove evento quando o agendamento
  é cancelado.

Os listeners implementam `ShouldQueue`, são configurados para executar após o
commit e têm cinco tentativas com espera progressiva. O evento
`app/Events/AgendamentoAtualizado.php` é disparado pelo model quando mudam
`data_hora`, `data_hora_fim` ou `observacoes` de um agendamento confirmado.

O model `Agendamento` já continha a coluna `google_event_id` no SQL de ajustes
disponível; por isso, nenhuma migration foi criada para esse campo.

**Limite conhecido:** o projeto não possui outbox transacional. Uma interrupção
entre o commit e a publicação do job pode deixar o Google temporariamente
desatualizado. A documentação recomenda avaliar uma outbox ou rotina de
reconciliação antes de produção.

### 4. Configuração e documentação da API

`config/google_calendar.php` e `.env.example` introduzem estas configurações:

```dotenv
GOOGLE_CALENDAR_ID=
GOOGLE_APPLICATION_CREDENTIALS=
```

O caminho de credenciais deve apontar para um JSON de service account guardado
fora do repositório. Nenhum segredo foi adicionado ao código ou à documentação.

`docs/openapi_Thyago.yaml` foi atualizado com as rotas administrativas,
parâmetros, filtros, respostas de autenticação/autorização, conflitos,
validação e falha da agenda externa.

`docs/README_Thyago.md` agora inclui:

- Checklist de Google Cloud, Calendar API, service account e compartilhamento
  da agenda.
- Configuração das variáveis de ambiente e execução da fila.
- Regras das novas rotas administrativas.
- Explicação do tratamento de erros e da lacuna de outbox.
- Bloqueio de anexos/fotos por falta do schema compartilhado.

### 5. Teste automatizado novo

`tests/Unit/GoogleCalendarServiceTest.php` cobre, com HTTP falso e uma chave
RSA efêmera criada durante o teste:

- Conversão da resposta free/busy para intervalos Carbon.
- Falha explícita quando a API indica problema de permissão.
- Reutilização do identificador determinístico após conflito na criação.

Não são necessárias credenciais reais do Google para esses cenários.

## Itens não implementados e dependências do grupo

### Anexos e fotos

Não foram criados endpoints ou migrations de upload. O projeto disponível não
inclui o SQL-base completo nem o schema da tabela `foto`. O SQL de ajustes cita
`anexo_agendamento` e menciona que `foto` continua responsável por imagens de
antes/depois, mas não define seus relacionamentos, colunas, limites, tipos de
imagem ou regras de acesso. É necessário alinhar esses pontos com o Dev A e o
grupo antes de codificar, para não duplicar tabelas ou inventar regras.

### Autenticação de produção

As novas rotas seguem o middleware existente `auth.stub:usuario` mais `admin`.
O stub usa identificador em header e só funciona localmente. A integração
definitiva com Sanctum e a autorização de perfis depende do trabalho do Dev A.

### Configuração externa pendente

Para usar o Google, o responsável pela agenda precisa:

1. Criar/selecionar o projeto no Google Cloud e ativar Calendar API.
2. Criar uma service account e baixar sua chave JSON em local privado.
3. Compartilhar a agenda com o e-mail da service account, dando permissão de
   edição de eventos.
4. Configurar `GOOGLE_CALENDAR_ID` e `GOOGLE_APPLICATION_CREDENTIALS`.
5. Configurar uma fila funcional e iniciar `php artisan queue:work`; em
   produção, usar o Supervisor da infraestrutura.
