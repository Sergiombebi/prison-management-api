<?php

namespace App\Enums;

enum CategoriePenale: string
{
    case Prevenus = 'prevenus';
    case Condamnes = 'condamnes';
    case Appellants = 'appellants';
    case Cassationnaires = 'cassationnaires';
    case Dpac = 'dpac';
}
