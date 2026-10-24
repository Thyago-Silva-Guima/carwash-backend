<?php

namespace App\Events;

use App\Models\Agendamento;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgendamentoConfirmado
{
    use Dispatchable, SerializesModels;

    public function __construct(public Agendamento $agendamento)
    {
    }
}
