<?php

// Valores PROVISÓRIOS: o cliente final vai definir os reais depois.
return [
    'passo_minutos'         => env('AGENDA_PASSO', 30),
    'antecedencia_agendar'  => env('AGENDA_ANTEC_AGENDAR_H', 2),
    'antecedencia_cancelar' => env('AGENDA_ANTEC_CANCELAR_H', 2),
    'limite_dias'           => env('AGENDA_LIMITE_DIAS', 90),
    'expirar_pendente_h'    => env('AGENDA_EXPIRAR_H', null), // desligado
];
