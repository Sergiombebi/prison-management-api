<?php

namespace App\Enums;

enum TypeSortieDetenu: string
{
    case LiberationNormale = 'liberation_normale';
    case Deces = 'deces';
    case Transfert = 'transfert';
    case Evasion = 'evasion';
}
