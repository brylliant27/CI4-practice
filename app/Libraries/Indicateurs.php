<?php

namespace App\Libraries;

use InvalidArgumentException;

/**
 * Calcul des indicateurs agro-climatiques : Fact_Indicateur = Séries ⊗ Seuils.
 *
 * Même recette pour tous (voir COMMENT-CODER-UN-INDICATEUR du kit) :
 *   1. entrées       : une variable des séries + un seuil de la culture ;
 *   2. règle du jour : une valeur par journée (1/0 pour compter, ou une quantité) ;
 *   3. année         : on additionne les valeurs du jour, année par année ;
 *   4. horizon       : moyenne des valeurs annuelles sur la période (10 ans).
 * D'un indicateur à l'autre, seule la règle du jour change.
 *
 * DÉCISIONS DE L'ÉQUIPE (docs/decisions.md) :
 *  - Journée AU seuil : elle compte (≤ pour le gel, ≥ pour la chaleur). Par prudence,
 *    on préfère signaler un risque de trop à un risque manqué.
 *  - Valeur manquante : la journée est ignorée (jamais remplacée par 0) et comptée
 *    dans « manquants » ; une année sans aucune donnée est exclue de la moyenne.
 *  - Année sans gel : elle vaut 0 et COMPTE dans la moyenne (ce n'est pas une donnée manquante).
 */
final class Indicateurs
{
    public const DEFINITIONS = [
        'jours_gel' => [
            'libelle'    => 'Jours de gel',
            'nom_court'  => 'jour de gel',
            'pluriel'    => 'jours de gel',
            'unite'      => 'jours/an',
            'variable'   => 't_min',
            'seuil'      => 'seuil_gel_c',
            'regle'      => 'température minimale ≤ seuil de gel de la culture',
            'agregation' => 'compter',
        ],
        'stress_thermique' => [
            'libelle'    => 'Jours de stress thermique',
            'nom_court'  => 'jour de stress thermique',
            'pluriel'    => 'jours de stress thermique',
            'unite'      => 'jours/an',
            'variable'   => 't_max',
            'seuil'      => 'seuil_stress_thermique_c',
            'regle'      => 'température maximale ≥ seuil de stress thermique de la culture',
            'agregation' => 'compter',
        ],
        'gdd' => [
            'libelle'    => 'Cumul de chaleur (GDD)',
            'nom_court'  => 'degré-jour',
            'unite'      => '°C·j/an',
            'variable'   => 't_moy',
            'seuil'      => 't_base_c',
            'regle'      => 'max(0, température moyenne − température de base), sur l\'année civile',
            'agregation' => 'additionner',
        ],
        'pluie' => [
            'libelle'    => 'Cumul de précipitations',
            'nom_court'  => 'mm',
            'unite'      => 'mm/an',
            'variable'   => 'precip',
            'seuil'      => null,
            'regle'      => 'somme des précipitations journalières',
            'agregation' => 'additionner',
        ],
    ];

    /** Vrai si la culture renseigne le seuil dont l'indicateur a besoin. */
    public static function calculable(string $code, array $culture): bool
    {
        $seuil = self::definition($code)['seuil'];

        return $seuil === null || (isset($culture[$seuil]) && is_numeric($culture[$seuil]));
    }

    /**
     * Étape 2 — la règle du jour. Retourne null si la donnée du jour manque.
     *
     * @param array<string, mixed> $jour une ligne des séries (contrat d'interface)
     */
    public static function valeurDuJour(string $code, array $jour, array $culture): float|int|null
    {
        $definition = self::definition($code);
        $x          = $jour[$definition['variable']] ?? null;

        // Indispensable : en PHP, null <= 0.0 est VRAI. Sans ce test, un jour sans
        // mesure serait compté comme un jour de gel.
        if ($x === null) {
            return null;
        }

        $seuil = $definition['seuil'] === null ? null : (float) $culture[$definition['seuil']];

        return match ($code) {
            'jours_gel'        => $x <= $seuil ? 1 : 0,
            'stress_thermique' => $x >= $seuil ? 1 : 0,
            'gdd'              => max(0.0, $x - $seuil),
            'pluie'            => $x,
        };
    }

    /**
     * Étape 3 — valeur de l'indicateur pour chaque année de l'horizon.
     *
     * @param list<array<string, mixed>> $series
     *
     * @return array<int, array{valeur: float|null, jours: int, manquants: int}>
     */
    public static function parAnnee(string $code, array $series, array $culture, int $anneeDebut, int $anneeFin): array
    {
        if (! self::calculable($code, $culture)) {
            throw new InvalidArgumentException("La culture ne renseigne pas le seuil nécessaire à « {$code} ».");
        }

        $annees = [];
        for ($a = $anneeDebut; $a <= $anneeFin; $a++) {
            $annees[$a] = ['total' => 0.0, 'jours' => 0, 'manquants' => 0];
        }

        foreach ($series as $jour) {
            $annee = (int) substr((string) $jour['date'], 0, 4);
            if (! isset($annees[$annee])) {
                continue; // journée hors de l'horizon
            }

            $valeur = self::valeurDuJour($code, $jour, $culture);
            if ($valeur === null) {
                $annees[$annee]['manquants']++;
            } else {
                $annees[$annee]['total'] += $valeur;
                $annees[$annee]['jours']++;
            }
        }

        return array_map(static fn (array $a) => [
            // Une année sans AUCUNE donnée n'a pas de valeur (et non 0).
            'valeur'    => $a['jours'] > 0 ? round($a['total'], 1) : null,
            'jours'     => $a['jours'],
            'manquants' => $a['manquants'],
        ], $annees);
    }

    /**
     * Étape 4 — moyenne annuelle sur l'horizon (années sans donnée exclues).
     *
     * @param array<int, array{valeur: float|null}> $parAnnee
     */
    public static function moyenne(array $parAnnee): ?float
    {
        $valeurs = array_filter(array_column($parAnnee, 'valeur'), static fn ($v) => $v !== null);

        return $valeurs === [] ? null : round(array_sum($valeurs) / count($valeurs), 1);
    }

    /**
     * Phrase d'évolution entre la référence et l'horizon.
     * On annonce l'ÉVOLUTION, seule information fiable (les nombres bruts d'un modèle sont biaisés).
     */
    public static function evolution(string $code, ?float $reference, ?float $futur): string
    {
        if ($reference === null || $futur === null) {
            return 'Évolution non calculable : données manquantes sur une des deux périodes.';
        }

        $definition = self::definition($code);
        $delta      = round($futur - $reference, 1);

        if ($definition['agregation'] === 'compter') {
            if (abs($delta) < 1) {
                return "Le nombre de {$definition['pluriel']} resterait globalement stable d'ici 2050 "
                    . '(' . fr_nombre($reference) . ' → ' . fr_nombre($futur) . ' ' . $definition['unite'] . ' selon le modèle).';
            }
            $n    = (int) round(abs($delta));
            $nom  = $n > 1 ? $definition['pluriel'] : $definition['nom_court'];
            $sens = $delta < 0 ? 'de moins' : 'de plus';

            return "Environ {$n} {$nom} {$sens} par an d'ici 2050 "
                . '(' . fr_nombre($reference) . ' → ' . fr_nombre($futur) . ' ' . $definition['unite'] . ' selon le modèle).';
        }

        $pourcent = $reference == 0 ? null : round(($futur - $reference) / abs($reference) * 100);

        return fr_nombre($reference) . ' → ' . fr_nombre($futur) . ' ' . $definition['unite']
            . ($pourcent === null ? '' : ' (' . ($pourcent > 0 ? '+' : '') . fr_nombre($pourcent, 0) . ' %)')
            . ' selon le modèle.';
    }

    /** @return array<string, string|null> */
    public static function definition(string $code): array
    {
        if (! isset(self::DEFINITIONS[$code])) {
            throw new InvalidArgumentException("Indicateur inconnu : {$code}");
        }

        return self::DEFINITIONS[$code];
    }
}
