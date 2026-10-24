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
