<?php

namespace App\Libraries;

use App\Models\CommuneModel;
use App\Models\CultureModel;
use App\Models\FactIndicateurModel;
use App\Models\ModeleModel;
use App\Models\TempsModel;
use InvalidArgumentException;
use RuntimeException;

/**
 * Le diagnostic d'une culture dans une commune, de bout en bout :
 *
 *   sélection ─► API Open-Meteo ─► séries (contrat v1.0) ─► module de calcul DATA (Python)
 *                     pôle DEV                                       │ indicateurs
 *   page de résultat ◄── relecture (étoile) ◄── fact_indicateur ◄────┘ (session du visiteur)
 *
 * Partage des rôles (énoncé §4) : DEV produit les séries, DATA produit les faits.
 * Les séries brutes ne sont pas stockées ; seuls les indicateurs (les faits) le sont,
 * et uniquement pour la session du visiteur.
 */
class ServiceDiagnostic
{
    private ClimateApi $api;
    private ModuleCalcul $calcul;

    public function __construct(?ClimateApi $api = null, ?ModuleCalcul $calcul = null)
    {
        $this->api    = $api ?? service('climateApi');
        $this->calcul = $calcul ?? new ModuleCalcul();
    }

    /**
     * @return array<string, mixed> les données de la page de résultat
     *
     * @throws InvalidArgumentException commune ou culture inconnue
     * @throws RuntimeException         API ou module de calcul indisponible, base non initialisée
     */
    public function executer(string $idSession, int $idCommune, int $idCulture): array
    {
        $commune = model(CommuneModel::class)->find($idCommune);
        $culture = model(CultureModel::class)->find($idCulture);
        if ($commune === null || $culture === null) {
            throw new InvalidArgumentException('Commune ou culture inconnue.');
        }

        $modele   = model(ModeleModel::class)->parCode(ClimateApi::MODELE_DEFAUT);
        $horizons = model(TempsModel::class)->horizons();
        if ($modele === null || count($horizons) < 2) {
            throw new RuntimeException('Base non initialisée : lancez « php spark db:seed ReferentielSeeder ».');
        }

        // 1-2 (DEV) : une série par horizon, au format du contrat d'interface
        $periodes = [];
        foreach ($horizons as $horizon) {
            $reponse    = $this->api->fetch(
                (float) $commune['latitude'],
                (float) $commune['longitude'],
                $horizon['annee_debut'] . '-01-01',
                $horizon['annee_fin'] . '-12-31',
                $modele['code']
            );
            $periodes[] = [
                'id_temps'    => (int) $horizon['id_temps'],
                'annee_debut' => (int) $horizon['annee_debut'],
                'annee_fin'   => (int) $horizon['annee_fin'],
                'series'      => SeriesClimat::depuisApi($idCommune, $reponse),
            ];
        }

        // 3-4 (DATA) : indicateurs année par année puis moyenne par horizon
        $calcules = $this->calcul->calculer($culture, $periodes);

        // 5 : écriture des faits de CETTE session pour CETTE sélection
        //     (un indicateur non calculable, faute de seuil, ne produit aucun fait)
        $faits = [];
        foreach ($calcules as $code => $indicateur) {
            foreach ($indicateur['calculable'] ? $indicateur['periodes'] : [] as $idTemps => $periode) {
                $faits[] = ['id_temps' => (int) $idTemps, 'code_indicateur' => $code, 'valeur' => $periode['moyenne']];
            }
        }
        $table = model(FactIndicateurModel::class);
        $table->remplacer($idSession, $idCommune, $idCulture, $modele['id_modele'], $faits);

        // 6 : relecture de la table de faits jointe à Dim_Temps (c'est elle qu'on affiche)
        $lus = $table->lire($idSession, $idCommune, $idCulture, $modele['id_modele']);

        return [
            'commune'     => $commune,
            'culture'     => $culture,
            'modele'      => $modele,
            'horizons'    => $horizons,
            'indicateurs' => $this->assembler($lus, $calcules, $culture),
        ];
    }

    /**
     * @param list<array<string, mixed>>           $lus      faits relus en base
     * @param array<string, array<string, mixed>>  $calcules réponse du module (détail année par année)
     *
     * @return array<string, array<string, mixed>>
     */
    private function assembler(array $lus, array $calcules, array $culture): array
    {
        $resultat = [];
        foreach (Indicateurs::ordre(array_keys($calcules)) as $code) {
            $calcule    = $calcules[$code];
            $definition = Indicateurs::definition($code);
            $lignes     = array_values(array_filter($lus, static fn (array $l) => $l['code_indicateur'] === $code));
            $seuil      = $definition['seuil'];

            $indicateur = $definition + [
                'code'         => $code,
                'libelle'      => $calcule['libelle'],
                'unite'        => $calcule['unite'],
                'calculable'   => $lignes !== [],
                'message'      => $calcule['message'] ?? null,
                'valeur_seuil' => $seuil === null ? null : ($culture[$seuil] ?? null),
                'periodes'     => [],
                'evolution'    => null,
            ];

            foreach ($lignes as $ligne) {
                $annees = [];
                foreach ($calcule['periodes'][(string) $ligne['id_temps']]['annees'] ?? [] as $annee => $a) {
                    $annees[(int) $annee] = $a;
                }
                $indicateur['periodes'][] = [
                    'libelle'      => $ligne['libelle'],
                    'annee_debut'  => (int) $ligne['annee_debut'],
                    'annee_fin'    => (int) $ligne['annee_fin'],
                    'type_periode' => $ligne['type_periode'],
                    'valeur'       => $ligne['valeur'] === null ? null : (float) $ligne['valeur'],
                    'annees'       => $annees,
                    'manquants'    => array_sum(array_column($annees, 'manquants')),
                ];
            }

            if ($indicateur['calculable']) {
                $reference = $indicateur['periodes'][0]['valeur'];
                $futur     = $indicateur['periodes'][count($indicateur['periodes']) - 1]['valeur'];

                $indicateur['evolution'] = Indicateurs::evolution($code, $reference, $futur, $indicateur['unite']);
            }

            $resultat[$code] = $indicateur;
        }

        return $resultat;
    }
}
