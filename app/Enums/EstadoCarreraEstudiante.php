<?php

declare(strict_types=1);

namespace App\Enums;

enum EstadoCarreraEstudiante: string
{
    case ACTIVO = 'activo';
    case EGRESADO = 'egresado';
    case TITULADO = 'titulado';
    case EN_RESERVA = 'en_reserva';
    case RETIRADO = 'retirado';
}
