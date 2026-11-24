<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Remplit les DIMENSIONS du schéma en étoile (la table de faits reste vide :
 * elle se remplit à chaque diagnostic).
 *
 * Les référentiels communes et cultures sont des CSV dans app/Database/Seeds/data/ :
 * le pôle DATA les met à jour SANS toucher au code, puis on relance
 *     php spark db:seed ReferentielSeeder
 * Le seeder est rejouable : il met à jour les lignes existantes (upsert).
 *
 * Il tolère ce qu'Excel produit à l'enregistrement : marque BOM, points-virgules,
 * encodage Windows, virgule décimale (43,60), zéro de tête supprimé (5023 → 05023).
 */
class ReferentielSeeder extends Seeder
{
    private const DOSSIER = APPPATH . 'Database/Seeds/data/';

    public function run()
    {
        $communes = array_map(fn (array $r) => [
            'id_commune'      => (int) $r['id_commune'],
            'nom'             => trim($r['nom']),
            'code_insee'      => self::codeInsee($r['code_insee'] ?? ''),
            'latitude'        => self::nombre($r['latitude']),
            'longitude'       => self::nombre($r['longitude']),
            'altitude_m'      => ($a = self::nombre($r['altitude_m'] ?? '')) === null ? null : (int) $a,
            'zone_climatique' => trim($r['zone_climatique'] ?? '') ?: null,
        ], self::lireCsv('communes-reference.csv'));

        $cultures = array_map(fn (array $r) => [
            'id_culture'               => (int) $r['id_culture'],
            'nom'                      => trim($r['nom']),
            't_base_c'                 => self::nombre($r['t_base_c'] ?? ''),
            'gdd_maturite_cj'          => self::nombre($r['gdd_maturite_cj'] ?? ''),
            'seuil_gel_c'              => self::nombre($r['seuil_gel_c'] ?? ''),
            'seuil_stress_thermique_c' => self::nombre($r['seuil_stress_thermique_c'] ?? ''),
            't_opt_min_c'              => self::nombre($r['t_opt_min_c'] ?? ''),
            't_opt_max_c'              => self::nombre($r['t_opt_max_c'] ?? ''),
            'besoin_eau_mm'            => self::nombre($r['besoin_eau_mm'] ?? ''),
            'source'                   => trim($r['source'] ?? '') ?: null,
        ], self::lireCsv('referentiel-cultures.csv'));

        // Deux horizons de 10 ans (même choix que le kit : quota Open-Meteo, et 10 ans
        // est le minimum pour qu'une moyenne climatique ait un sens).
        $temps = [
            ['id_temps' => 1, 'libelle' => 'Référence récente', 'annee_debut' => 2011, 'annee_fin' => 2020, 'type_periode' => 'reference'],
            ['id_temps' => 2, 'libelle' => 'Horizon 2050', 'annee_debut' => 2041, 'annee_fin' => 2050, 'type_periode' => 'projection'],
        ];

        // Un seul modèle pour le sprint 1 : AUCUN indice de confiance n'est donc affiché.
        $modeles = [
            ['id_modele' => 1, 'code' => 'MRI_AGCM3_2_S', 'libelle' => 'MRI-AGCM3-2-S',
                'institut' => 'Meteorological Research Institute, Japon', 'resolution_km' => 20],
        ];

        $this->db->table('dim_commune')->upsertBatch($communes);
        $this->db->table('dim_culture')->upsertBatch($cultures);
        $this->db->table('dim_temps')->upsertBatch($temps);
        $this->db->table('dim_modele')->upsertBatch($modeles);

        if (is_cli() && ENVIRONMENT !== 'testing') {
            echo count($communes) . ' communes, ' . count($cultures) . ' cultures, '
                . count($temps) . ' horizons, ' . count($modeles) . " modèle(s).\n";
        }
    }

    /** @return list<array<string, string>> */
    public static function lireCsv(string $fichier): array
    {
        $chemin = self::DOSSIER . $fichier;
        if (! is_file($chemin)) {
            throw new RuntimeException("Référentiel introuvable : {$chemin}");
        }

        $texte = file_get_contents($chemin);
        $texte = preg_replace('/^\xEF\xBB\xBF/', '', $texte); // marque BOM d'Excel
        if (! mb_check_encoding($texte, 'UTF-8')) {
            $texte = mb_convert_encoding($texte, 'UTF-8', 'Windows-1252');
        }

        $lignes = preg_split('/\R/', trim($texte));
        $sep    = substr_count($lignes[0], ';') > substr_count($lignes[0], ',') ? ';' : ',';
        $entete = array_map('trim', str_getcsv($lignes[0], $sep, '"', ''));

        $rows = [];
        foreach (array_slice($lignes, 1) as $ligne) {
            if (trim($ligne) === '') {
                continue;
            }
            $valeurs = str_getcsv($ligne, $sep, '"', '');
            $rows[]  = array_combine($entete, array_pad($valeurs, count($entete), ''));
        }

        return $rows;
    }

    /** « 43,60 » ou « 43.60 » → 43.6 ; vide ou non numérique → null (valeur non renseignée). */
    public static function nombre(?string $valeur): ?float
    {
        $v = str_replace(',', '.', trim((string) $valeur));

        return is_numeric($v) ? (float) $v : null;
    }

    public static function codeInsee(string $valeur): ?string
    {
        $v = trim($valeur);

        return $v === '' ? null : (ctype_digit($v) ? str_pad($v, 5, '0', STR_PAD_LEFT) : $v);
    }
}
