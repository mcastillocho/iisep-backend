<?php

declare(strict_types=1);

namespace App\Enums;

enum EnrollmentStatus: string
{
    case PREINSCRITO = 'preinscrito';
    case ACTIVO = 'activo';
    case COMPLETADO = 'completado';
    case RETIRADO = 'retirado';
    case ANULADO = 'anulado';
}
