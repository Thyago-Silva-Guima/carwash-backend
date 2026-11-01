#!/usr/bin/env bash
# Cria a estrutura do módulo Disponibilidade + Agendamento (Dev B).
# Rode na RAIZ do projeto Laravel (a pasta onde está o arquivo "artisan"):
#   bash criar_estrutura_dev_b.sh

[ -f artisan ] || { echo "Rode este script na raiz do projeto Laravel (onde está o artisan)."; exit 1; }

# Roda o artisan sem perguntas e não para se o arquivo já existir.
mk() { php artisan "$@" --no-interaction || true; }

# Cria um arquivo só se ele ainda não existir (conteúdo vem do stdin).
novo() {
  if [ -e "$1" ]; then echo "já existe: $1"; return; fi
  mkdir -p "$(dirname "$1")"
  cat > "$1"
  echo "criado: $1"
}

echo "== PASSO 1: contratos, config e stubs =="

novo app/Contracts/AgendaExterna.php <<'PHP'
<?php

namespace App\Contracts;

use Carbon\Carbon;

/**
 * CONTRATO fixo entre Dev B e Dev C. Não mudar nome nem assinatura.
 * Dev B usa; Dev C implementa com o Google Agenda.
 */
interface AgendaExterna
{
    /** @return array<int, array{inicio: Carbon, fim: Carbon}> */
    public function ocupados(Carbon $inicio, Carbon $fim): array;
}
PHP

novo app/Services/Agendamento/AgendaExternaVazia.php <<'PHP'
<?php

namespace App\Services\Agendamento;

use App\Contracts\AgendaExterna;
use Carbon\Carbon;

/** STUB: descartar na integração (o Dev C registra a versão do Google). */
class AgendaExternaVazia implements AgendaExterna
{
    public function ocupados(Carbon $inicio, Carbon $fim): array
    {
        return [];
    }
}
PHP

novo config/agendamento.php <<'PHP'
<?php

// Valores PROVISÓRIOS: o cliente final vai definir os reais depois.
return [
    'passo_minutos'         => env('AGENDA_PASSO', 30),
    'antecedencia_agendar'  => env('AGENDA_ANTEC_AGENDAR_H', 2),
    'antecedencia_cancelar' => env('AGENDA_ANTEC_CANCELAR_H', 2),
    'limite_dias'           => env('AGENDA_LIMITE_DIAS', 90),
    'expirar_pendente_h'    => env('AGENDA_EXPIRAR_H', null), // desligado
];
PHP

mk make:event AgendamentoConfirmado
mk make:event AgendamentoCancelado
mk make:middleware AuthStubLocal   # STUB: login falso por header, só em ambiente local

echo "== PASSO 2: testes (escreva antes do código) =="
mk make:test Unit/CalculoHorariosTest --unit
mk make:test Feature/JanelasTest
mk make:test Feature/AgendamentoTest
mk make:test Feature/ConcorrenciaTest
mk make:test Feature/CancelamentoTest

echo "== PASSO 3: janelas do dono =="
mk make:model Disponibilidade
mk make:migration create_disponibilidade_table --create=disponibilidade
mk make:request Disponibilidade/StoreDisponibilidadeRequest
mk make:request Disponibilidade/UpdateDisponibilidadeRequest
mk make:request Disponibilidade/LoteDisponibilidadeRequest
mk make:policy DisponibilidadePolicy --model=Disponibilidade
mk make:controller Disponibilidade/JanelaController --api
mk make:controller Disponibilidade/LoteController --invokable

echo "== PASSO 4: cálculo de horários livres =="
mk make:controller Disponibilidade/HorariosController
novo app/Services/Agendamento/DisponibilidadeService.php <<'PHP'
<?php

namespace App\Services\Agendamento;

class DisponibilidadeService
{
    // horariosLivres(data, duracao) e diasComVaga(mes, duracao): passo 4
}
PHP

echo "== PASSOS 5 e 6: agendamento, histórico e cancelamento =="
mk make:model Agendamento
mk make:request Agendamento/StoreAgendamentoRequest
mk make:policy AgendamentoPolicy --model=Agendamento
mk make:controller Agendamento/AgendamentoController --api
mk make:controller Agendamento/CancelamentoController --invokable
novo app/Services/Agendamento/AgendamentoService.php <<'PHP'
<?php

namespace App\Services\Agendamento;

class AgendamentoService
{
    // criar(): transação + lockForUpdate (passo 5); cancelar(): passo 6
}
PHP

echo "== STUBS: models mínimos de OUTROS devs (descartar na integração) =="
mk make:model Servico
mk make:model Cliente
mk make:model Endereco
mk make:model AgendamentoServico

echo "== ROTAS E DOCS =="
novo routes/disponibilidade.php <<'PHP'
<?php

use Illuminate\Support\Facades\Route;

// Cliente: GET /disponibilidade/dias, GET /disponibilidade,
//          POST /agendamentos, GET /agendamentos, GET /agendamentos/{id},
//          PATCH /agendamentos/{id}/cancelar
// Admin:   /admin/disponibilidades (CRUD), POST /admin/disponibilidades/lote
PHP

novo docs/README_DEV_B.md <<'MD'
# Módulo Disponibilidade + Agendamento (Dev B)

## Stubs a trocar na integração
- app/Http/Middleware/AuthStubLocal.php (trocar por auth:sanctum)
- app/Services/Agendamento/AgendaExternaVazia.php (Dev C registra a do Google)
- Models mínimos: Servico, Cliente, Endereco, AgendamentoServico (usar os do Dev A)

## Arquivos comuns do Laravel alterados
- app/Providers/AppServiceProvider.php (bind da AgendaExterna)
- bootstrap/app.php (carregar routes/disponibilidade.php e alias do middleware stub)
MD

novo docs/openapi_dev_b.yaml <<'YAML'
# Trecho do OpenAPI com as rotas do Dev B (preencher no passo 7)
YAML

echo
echo "Estrutura criada. Faltam 2 edições manuais em arquivos comuns:"
echo "  1) app/Providers/AppServiceProvider.php -> bind(AgendaExterna::class, AgendaExternaVazia::class)"
echo "  2) bootstrap/app.php -> carregar routes/disponibilidade.php com prefixo /api"
