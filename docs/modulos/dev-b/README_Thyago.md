# Módulo Disponibilidade + Agendamento (Dev B)

Parte do back end responsável pelas janelas de atendimento cadastradas pelo dono,
pelo cálculo de horários livres e pelo agendamento feito pelo cliente.

## Como rodar localmente
1. Laragon ligado (Start All) e banco `car_wash` com o `Db_Car_Wash.sql` + `ajustes.sql`.
2. `.env` com `DB_CONNECTION=mysql`, `DB_DATABASE=car_wash`, `SESSION_DRIVER=file`,
   `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`.
3. Migration da disponibilidade (somente ela):
   `php artisan migrate --path=database/migrations/<arquivo>_create_disponibilidade_table.php`
4. `php artisan serve` e usar a coleção `DevB.postman_collection.json`.

## Login falso (STUB, só em ambiente local)
- Cliente: header `X-Cliente-Id: 1`
- Admin: header `X-Usuario-Id: 1` (o usuário precisa ter `perfil = admin`)
- Sempre enviar `Accept: application/json`.

## Regras de negócio implementadas
- O dono cadastra janelas (data, início, fim, capacidade). Janelas do mesmo dia não podem se sobrepor.
- Lote: cria janelas num período, só nos dias da semana escolhidos (0 = domingo ... 6 = sábado).
- Editar ou apagar janela com agendamentos retorna 409 com a lista dos agendamentos afetados.
- Horário livre = o serviço cabe inteiro numa janela, não passa da capacidade (conta pendente e
  confirmado), não colide com a agenda externa e respeita antecedência e limite futuro.
- O agendamento nasce `pendente` e bloqueia o horário. Preço, duração e valor total são
  calculados no servidor.
- Criar agendamento: 1ª checagem com a agenda externa; depois transação com `lockForUpdate`
  nas janelas do dia e 2ª checagem só no banco.
- Cancelamento pelo cliente exige antecedência mínima e dispara `AgendamentoCancelado`.

## Parâmetros provisórios (`config/agendamento.php`)
| Parâmetro | Valor |
|---|---|
| passo entre horários | 30 min |
| antecedência para agendar | 2 h |
| antecedência para cancelar | 2 h (unidade a confirmar) |
| limite futuro | 90 dias |
| duração e preço dos serviços | provisórios (ajustes.sql) |

## Contratos com os outros devs (não mudar)
- `App\Contracts\AgendaExterna::ocupados(Carbon $inicio, Carbon $fim): array`
  (itens `['inicio' => Carbon, 'fim' => Carbon]`). Dev C implementa com o Google.
- Eventos `App\Events\AgendamentoConfirmado` e `AgendamentoCancelado` (recebem o model `Agendamento`).
- Status: `pendente`, `confirmado`, `concluido`, `cancelado`.

## STUBS a trocar na integração
- `app/Http/Middleware/AuthStubLocal.php` -> trocar `auth.stub:cliente` e `auth.stub:usuario`
  por `auth:sanctum` em `routes/disponibilidade.php`.
- `app/Services/Agendamento/AgendaExternaVazia.php` -> Dev C registra a implementação do Google
  no `AppServiceProvider`.
- Models mínimos do Dev A: `Cliente`, `Usuario`, `Servico`, `Endereco`.
- Policies geradas (`AgendamentoPolicy`, `DisponibilidadePolicy`) não são usadas; podem ser apagadas.

## Arquivos comuns do Laravel alterados
- `app/Providers/AppServiceProvider.php` (bind da `AgendaExterna`)
- `bootstrap/app.php` (carrega `routes/disponibilidade.php` e registra os apelidos de middleware)
- `config/app.php` (`timezone` = `America/Sao_Paulo`)
- `.env` (conexão MySQL, sessão, cache e fila)

## Pendências conhecidas
- Editar a capacidade de uma janela para menos que os agendamentos já feitos ainda não é impedido.
- O agendamento precisa caber dentro de UMA janela (janelas coladas não se somam).
- Mensagens de erro ainda em português simples, sem arquivos de idioma.

## Painel administrativo e integração Dev C

Rotas administrativas de agendamento ficam sob `/api/admin/agendamentos` e usam
os middlewares existentes `auth.stub:usuario` e `admin`. O stub continua limitado
ao ambiente local; em produção, a integração com a autenticação Sanctum do Dev A
precisa ser feita sem alterar o contrato destas ações.

- `GET /api/admin/agendamentos`: filtros por status, datas, cliente, serviço e atendente; paginação Laravel (`per_page` de 1 a 100).
- `GET /api/admin/agendamentos/resumo`: contagem de pendentes.
- `GET /api/admin/agendamentos/{id}`: cliente, endereço, serviços e atendente.
- `PATCH /api/admin/agendamentos/{id}/confirmar`: confirma somente pendentes, atribui o usuário administrativo autenticado e preserva o valor calculado na criação.
- `PATCH /api/admin/agendamentos/{id}/recusar`: aceita `motivo` obrigatório e recusa somente pendentes.
- `PATCH /api/admin/agendamentos/{id}/concluir`: conclui somente confirmados.

Transições inválidas retornam 409. As mudanças de estado usam transação e lock
da linha do agendamento. Confirmação e recusa disparam os eventos de domínio já
existentes fora da transação. Não há regra de negócio aprovada para recalcular
ou substituir o preço na confirmação; por isso, o total e os preços unitários
persistidos na criação são mantidos.

### Google Calendar

O binding de `AgendaExterna` usa `GoogleCalendarAgenda`. Credenciais são lidas de
um arquivo de service account apontado por `GOOGLE_APPLICATION_CREDENTIALS`; o
ID da agenda fica em `GOOGLE_CALENDAR_ID`. O arquivo JSON da chave não deve ser
versionado. A integração usa free/busy para a disponibilidade, eventos com ID
determinístico para idempotência e listeners enfileirados, disparados após o
commit. Uma falha de consulta retorna 503: o sistema não considera a agenda
livre automaticamente.

Para configurar:
1. Criar/selecionar um projeto no Google Cloud e ativar Google Calendar API.
2. Criar uma service account e baixar sua chave JSON para local privado fora do repositório.
3. Compartilhar a agenda desejada com o e-mail da service account, concedendo permissão para consultar disponibilidade e criar/editar/remover eventos (normalmente “Fazer alterações nos eventos”).
4. Copiar o ID da agenda e configurar `GOOGLE_CALENDAR_ID` e `GOOGLE_APPLICATION_CREDENTIALS`.
5. Configurar `QUEUE_CONNECTION=database`, criar as tabelas de fila conforme o ambiente e executar `php artisan queue:work`. Em produção, usar o Supervisor gerenciado pela infraestrutura.

Variáveis opcionais de token/API estão em `config/google_calendar.php`. Falhas dos
listeners são tentadas novamente cinco vezes com espera crescente. Eventos que
já não existem no Google são tratados como removidos; indisponibilidade e erros
de permissão permanecem como falha, sem registrar tokens ou chaves nos logs.
O despacho ocorre depois do commit, mas ainda não existe outbox transacional;
uma queda do processo entre o commit e a publicação na fila pode deixar a
agenda externa desatualizada. Para produção, alinhar com a infraestrutura uma
outbox ou rotina periódica de reconciliação entre agendamentos confirmados e
eventos Google.

### Bloqueio para anexos e fotos

Não foram adicionados endpoints nem migrations de arquivos nesta etapa: o
repositório não contém o SQL-base mencionado acima nem o schema da tabela `foto`.
O `ajustes.sql` informa apenas as colunas da tabela `anexo_agendamento` e que
`foto` continua responsável pelas imagens de antes/depois; não informa o
relacionamento, nomes de colunas, tipo de foto, regras de acesso ou limites de
upload. Antes de implementar essa parte, alinhar esses detalhes com o Dev A e
com o grupo para reutilizar as tabelas existentes e evitar uma estrutura
concorrente.
