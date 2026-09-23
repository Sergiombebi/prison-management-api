<?php

namespace App\Enums;

enum Permission: string
{
    case TableauBordConsulter = 'tableau_bord.consulter';

    case DetenusConsulter = 'detenus.consulter';
    case DetenusCreer = 'detenus.creer';
    case DetenusModifier = 'detenus.modifier';
    case DetenusDesactiver = 'detenus.desactiver';
    case DetenusRestaurer = 'detenus.restaurer';
    case DetenusMandatsGerer = 'detenus.mandats.gerer';
    case DetenusSortiesEnregistrer = 'detenus.sorties.enregistrer';

    case DisciplineCellulesConsulter = 'discipline.cellules.consulter';
    case DisciplineCellulesGerer = 'discipline.cellules.gerer';
    case DisciplineAffectationsGerer = 'discipline.affectations.gerer';
    case DisciplineSanctionsConsulter = 'discipline.sanctions.consulter';
    case DisciplineSanctionsCreer = 'discipline.sanctions.creer';
    case DisciplineSanctionsModifier = 'discipline.sanctions.modifier';
    case DisciplineSanctionsTerminer = 'discipline.sanctions.terminer';
    case DisciplineSanctionsAnnuler = 'discipline.sanctions.annuler';
    case DisciplineTypesSanctionGerer = 'discipline.types_sanction.gerer';

    case SanteConsultationsConsulter = 'sante.consultations.consulter';
    case SanteConsultationsCreer = 'sante.consultations.creer';
    case SanteDossierMedicalGerer = 'sante.dossier_medical.gerer';
    case SanteEvacuationsConsulter = 'sante.evacuations.consulter';
    case SanteEvacuationsCreer = 'sante.evacuations.creer';

    case VisitesConsulter = 'visites.consulter';
    case VisitesCreer = 'visites.creer';

    case EtatsConsulter = 'etats.consulter';

    case AdministrationPersonnelGerer = 'administration.personnel.gerer';
    case AdministrationParametresGerer = 'administration.parametres.gerer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
