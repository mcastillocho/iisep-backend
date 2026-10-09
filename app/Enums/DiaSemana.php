<?php

declare(strict_types=1);

namespace App\Enums;

enum DiaSemana: string
{
    case LUNES = 'lunes';
    case MARTES = 'martes';
    case MIERCOLES = 'miercoles';
    case JUEVES = 'jueves';
    case VIERNES = 'viernes';
    case SABADO = 'sabado';
    case DOMINGO = 'domingo';
}
