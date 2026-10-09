<?php

declare(strict_types=1);

namespace App\Enums;

enum TipoDocumento: string
{
    case DNI = 'dni';
    case CE = 'ce';
    case PASAPORTE = 'pasaporte';
    case RUC = 'ruc';
}
