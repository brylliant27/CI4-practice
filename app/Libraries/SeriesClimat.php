<?php

namespace App\Libraries;

use RuntimeException;
use UnexpectedValueException;

/**
 * Séries climatiques au format du CONTRAT D'INTERFACE (docs/contrat-interface.md).
 *
 * Frontière des rôles : le pôle DEV produit les SÉRIES (ce fichier), le pôle DATA
 * produit les FAITS (les indicateurs). Une série = une ligne par commune et par jour :
 *
 *     id_commune, date, t_min, t_max, t_moy, precip, et0
 *
 * Une valeur manquante vaut null (cellule vide en CSV) : elle n'est JAMAIS remplacée
 * par 0, sinon un jour sans mesure deviendrait un jour de gel.
 */
final class SeriesClimat
{
    public const VERSION_CONTRAT = '1.0';
    public const DATE_CONTRAT    = '2026-11-24';

    /** Colonne du contrat => variable Open-Meteo correspondante. */
    public const COLONNES = [
        'id_commune' => null,
        'date'       => 'time',
        't_min'      => 'temperature_2m_min',
        't_max'      => 'temperature_2m_max',
        't_moy'      => 'temperature_2m_mean',
        'precip'     => 'precipitation_sum',
        'et0'        => 'et0_fao_evapotranspiration_sum',
    ];

    public const UNITES = [
        'id_commune' => 'identifiant (dim_commune)',
        'date'       => 'AAAA-MM-JJ',
        't_min'      => '°C',
        't_max'      => '°C',
        't_moy'      => '°C',
        'precip'     => 'mm/jour',
        'et0'        => 'mm/jour',
    ];

    /**
     * Transforme la réponse brute d'Open-Meteo en séries du contrat.
     *
     * @param array<string, mixed> $reponse
     *
     * @return list<array<string, int|float|string|null>>
     */
    public static function depuisApi(int $idCommune, array $reponse): array
    {
        $daily = $reponse['daily'] ?? null;
        if (! is_array($daily) || ! isset($daily['time']) || ! is_array($daily['time'])) {
            throw new UnexpectedValueException('Réponse Open-Meteo sans bloc « daily.time ».');
        }

        $nbJours = count($daily['time']);
        foreach (self::COLONNES as $variable) {
            if ($variable !== null && isset($daily[$variable]) && count($daily[$variable]) !== $nbJours) {
                throw new UnexpectedValueException("La variable {$variable} n'a pas autant de valeurs que de jours.");
            }
        }

        $series = [];
        for ($i = 0; $i < $nbJours; $i++) {
            $ligne = ['id_commune' => $idCommune, 'date' => self::date($daily['time'][$i])];
            foreach (self::COLONNES as $colonne => $variable) {
                if ($variable !== null && $variable !== 'time') {
                    // Variable absente de la réponse => toute la colonne est manquante (null).
                    $ligne[$colonne] = self::nombre($daily[$variable][$i] ?? null);
                }
            }
            $series[] = $ligne;
        }

        return $series;
    }

    /** @param list<array<string, mixed>> $series */
    public static function versCsv(array $series): string
    {
        $flux = fopen('php://temp', 'r+');
        fputcsv($flux, array_keys(self::COLONNES), ',', '"', '');
        foreach ($series as $ligne) {
            $cellules = [];
            foreach (array_keys(self::COLONNES) as $colonne) {
                $cellules[] = self::formater($ligne[$colonne] ?? null);
            }
            fputcsv($flux, $cellules, ',', '"', '');
        }
        rewind($flux);
        $csv = stream_get_contents($flux);
        fclose($flux);

        return $csv;
    }

    /**
     * Lit un fichier CSV au format du contrat (pour les tests et le pôle DATA).
     *
     * @return list<array<string, int|float|string|null>>
     */
    public static function lireCsv(string $chemin): array
    {
        $flux = @fopen($chemin, 'rb');
        if ($flux === false) {
            throw new RuntimeException("Fichier de séries introuvable : {$chemin}");
        }

        $entete = fgetcsv($flux, null, ',', '"', '');
        if ($entete !== array_keys(self::COLONNES)) {
            fclose($flux);

            throw new UnexpectedValueException('En-tête non conforme au contrat : attendu '
                . implode(',', array_keys(self::COLONNES)));
        }

        $series = [];
        while (($cellules = fgetcsv($flux, null, ',', '"', '')) !== false) {
            if ($cellules === [null]) {
                continue; // ligne vide
            }
            $ligne = array_combine($entete, $cellules);
            $serie = ['id_commune' => (int) $ligne['id_commune'], 'date' => self::date($ligne['date'])];
            foreach (array_slice($entete, 2) as $colonne) {
                $serie[$colonne] = self::nombre($ligne[$colonne]);
            }
            $series[] = $serie;
        }
        fclose($flux);

        return $series;
    }

    private static function date(mixed $valeur): string
    {
        if (! is_string($valeur) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
            throw new UnexpectedValueException('Date non conforme (attendu AAAA-MM-JJ) : ' . var_export($valeur, true));
        }

        return $valeur;
    }

    private static function nombre(mixed $valeur): ?float
    {
        return is_numeric($valeur) ? (float) $valeur : null;
    }

    private static function formater(mixed $valeur): string
    {
        if ($valeur === null) {
            return '';
        }
        if (is_float($valeur)) {
            return $valeur == 0 ? '0' : (string) round($valeur, 2);
        }

        return (string) $valeur;
    }
}
