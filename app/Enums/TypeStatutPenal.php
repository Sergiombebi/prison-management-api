<?php

namespace App\Enums;

enum TypeStatutPenal: string
{
    case DetentionProvisoire = 'Détention provisoire';
    case ExecutionDePeine = 'Exécution de peine';
    case Appellant = 'Appellant';
    case Cassationnaire = 'Cassationnaire';
}
