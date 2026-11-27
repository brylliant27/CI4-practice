<?php

namespace App\Libraries;

/**
 * AFFICHAGE des indicateurs agro-climatiques (pôle DEV).
 *
 * Le CALCUL n'est plus ici : il appartient au pôle DATA (calcul/indicateurs.py), appelé
 * par ModuleCalcul. Cette classe ne fait que présenter les résultats : libellés au
 * singulier/pluriel, règle du jour expliquée à l'utilisateur, phrase d'évolution.
 *
 * Les codes sont ceux de calcul/indicateurs.py (stockés dans fact_indicateur.code_indicateur).
 * Un indicateur ajouté par DATA sans entrée ici s'affiche quand même, avec son libellé et
 * son unité venus du Python (voir definition()).
 */
final class Indicateurs
{
    public const DEFINITIONS = [
        'jours_gel' => [
            'nom_court'  => 'jour de gel',
            'pluriel'    => 'jours de gel',
            'seuil'      => 'seuil_gel_c',
            'regle'      => 'température minimale strictement sous le seuil de gel de la culture',
            'agregation' => 'compter',
        ],
        'jours_stress' => [
            'nom_court'  => 'jour de stress thermique',
            'pluriel'    => 'jours de stress thermique',
            'seuil'      => 'seuil_stress_thermique_c',
            'regle'      => 'température maximale ≥ seuil de stress thermique de la culture',
            'agregation' => 'compter',
        ],
        'gdd' => [
            'nom_court'  => 'degré-jour',
            'pluriel'    => 'degrés-jours',
            'seuil'      => 't_base_c',
            'regle'      => 'max(0, (température max + température min) / 2 − température de base), sur l\'année civile',
            'agregation' => 'additionner',
        ],
        'cumul_pluie' => [
            'nom_court'  => 'mm',
            'pluriel'    => 'mm',
            'seuil'      => null,
            'regle'      => 'somme des précipitations journalières',
            'agregation' => 'additionner',
        ],
    ];

    /** Ordre d'affichage : ceux décrits ici d'abord, puis ceux que DATA aurait ajoutés. */
    public static function ordre(array $codes): array
    {
        $connus = array_values(array_intersect(array_keys(self::DEFINITIONS), $codes));

        return [...$connus, ...array_values(array_diff($codes, $connus))];
    }

    /**
     * Phrase d'évolution entre la référence et l'horizon.
     * On annonce l'ÉVOLUTION, seule information fiable (les nombres bruts d'un modèle sont biaisés).
     */
    public static function evolution(string $code, ?float $reference, ?float $futur, string $unite = ''): string
    {
        if ($reference === null || $futur === null) {
            return 'Évolution non calculable : données manquantes sur une des deux périodes.';
        }

        $definition = self::definition($code);
        $unite      = $unite !== '' ? $unite : ($definition['unite'] ?? '');
        $delta      = round($futur - $reference, 1);

        if ($definition['agregation'] === 'compter') {
            if (abs($delta) < 1) {
                return "Le nombre de {$definition['pluriel']} resterait globalement stable d'ici 2050 "
                    . '(' . fr_nombre($reference) . ' → ' . fr_nombre($futur) . ' ' . $unite . ' selon le modèle).';
            }
            $n    = (int) round(abs($delta));
            $nom  = $n > 1 ? $definition['pluriel'] : $definition['nom_court'];
            $sens = $delta < 0 ? 'de moins' : 'de plus';

            return "Environ {$n} {$nom} {$sens} par an d'ici 2050 "
                . '(' . fr_nombre($reference) . ' → ' . fr_nombre($futur) . ' ' . $unite . ' selon le modèle).';
        }

        $pourcent = $reference == 0 ? null : round(($futur - $reference) / abs($reference) * 100);

        return fr_nombre($reference) . ' → ' . fr_nombre($futur) . ' ' . $unite
            . ($pourcent === null ? '' : ' (' . ($pourcent > 0 ? '+' : '') . fr_nombre($pourcent, 0) . ' %)')
            . ' selon le modèle.';
    }

    /**
     * Présentation d'un indicateur. Code inconnu (ajouté par DATA, pas encore décrit ici) :
     * présentation neutre plutôt qu'une erreur.
     *
     * @return array<string, string|null>
     */
    public static function definition(string $code): array
    {
        return self::DEFINITIONS[$code] ?? [
            'nom_court'  => $code,
            'pluriel'    => $code,
            'seuil'      => null,
            'regle'      => '—',
            'agregation' => 'additionner',
        ];
    }
}
