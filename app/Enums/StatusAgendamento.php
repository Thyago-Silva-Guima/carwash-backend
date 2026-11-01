<?php

namespace App\Enums;

enum StatusAgendamento: string
{
    case Pendente = 'pendente';
    case Confirmado = 'confirmado';
    case Concluido = 'concluido';
    case Cancelado = 'cancelado';
}
