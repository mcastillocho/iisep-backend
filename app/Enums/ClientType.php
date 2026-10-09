<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientType: string
{
    case ESTUDIANTE = 'estudiante';
    case APODERADO = 'apoderado';
    case DOCENTE = 'docente';
    case PARTICULAR = 'particular';
}
