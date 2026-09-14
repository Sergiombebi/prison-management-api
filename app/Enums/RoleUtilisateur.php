<?php

namespace App\Enums;

enum RoleUtilisateur: string
{
    case Admin = 'admin';
    case Agent = 'agent';
    case Medecin = 'medecin';
}
