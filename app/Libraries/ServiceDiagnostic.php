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
 *   sélection ─► API Open-Meteo ─► séries (contrat) ─► indicateurs ─► fact_indicateur
 *                                                                          │ relecture
 *                                                     page de résultat ◄───┘ (étoile)
 *
 * Les séries brutes ne sont pas stockées ; seuls les indicateurs (les faits) le sont,
 * et uniquement pour la session du visiteur.
 */
class ServiceDiagnostic
{
    private ClimateApi $api;

    public function __construct(?ClimateApi $api = null)
    {
        $this->api = $api ?? service('climateApi');
    }

    /**
     * @return array<string, mixed> les données de la page de résultat
     *
     * @throws InvalidArgumentException commune ou culture inconnue
     * @throws RuntimeException         API indisponible ou base non initialisée
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

        // 1 à 4 : séries de chaque horizon → indicateurs année par année → moyenne
        $faits   = [];
        $annuels = [];
        foreach ($horizons as $horizon) {
            $reponse = $this->api->fetch(
                (float) $commune['latitude'],
                (float) $commune['longitude'],
                $horizon['annee_debut'] . '-01-01',
                $horizon['annee_fin'] . '-12-31',
                $modele['code']
            );
            $series = SeriesClimat::depuisApi($idCommune, $reponse);

            foreach (array_keys(Indicateurs::DEFINITIONS) as $code) {
                if (! Indicateurs::calculable($code, $culture)) {
                    continue; // seuil non renseigné : on n'invente pas de fait
                }
                $parAnnee = Indicateurs::parAnnee($code, $series, $culture, $horizon['annee_debut'], $horizon['annee_fin']);

                $annuels[$code][$horizon['id_temps']] = $parAnnee;
                $faits[] = [
                    'id_temps'        => $horizon['id_temps'],
                    'code_indicateur' => $code,
                    'valeur'          => Indicateurs::moyenne($parAnnee),
                ];
            }
        }

        // 5 : écriture des faits de CETTE session pour CETTE sélection
        $table = model(FactIndicateurModel::class);
        $table->remplacer($idSession, $idCommune, $idCulture, $modele['id_modele'], $faits);

        // 6 : relecture de la table de faits jointe à Dim_Temps (c'est elle qu'on affiche)
        $lus = $table->lire($idSession, $idCommune, $idCulture, $modele['id_modele']);

        return [
            'commune'     => $commune,
            'culture'     => $culture,
            'modele'      => $modele,
            'horizons'    => $horizons,
            'indicateurs' => $this->assembler($lus, $annuels, $culture),
        ];
    }

    /**
     * @param list<array<string, mixed>>                 $lus
     * @param array<string, array<int, array<int, mixed>>> $annuels
     *
     * @return array<string, array<string, mixed>>
     */
    private function assembler(array $lus, array $annuels, array $culture): array
    {
        $resultat = [];
        foreach (Indicateurs::DEFINITIONS as $code => $definition) {
            $lignes = array_values(array_filter($lus, static fn (array $l) => $l['code_indicateur'] === $code));
            $seuil  = $definition['seuil'];

            $indicateur = $definition + [
                'code'         => $code,
                'calculable'   => $lignes !== [],
                'valeur_seuil' => $seuil === null ? null : $culture[$seuil],
                'periodes'     => [],
                'evolution'    => null,
            ];

            foreach ($lignes as $ligne) {
                $parAnnee                 = $annuels[$code][(int) $ligne['id_temps']] ?? [];
                $indicateur['periodes'][] = [
                    'libelle'      => $ligne['libelle'],
                    'annee_debut'  => (int) $ligne['annee_debut'],
                    'annee_fin'    => (int) $ligne['annee_fin'],
                    'type_periode' => $ligne['type_periode'],
                    'valeur'       => $ligne['valeur'] === null ? null : (float) $ligne['valeur'],
                    'annees'       => $parAnnee,
                    'manquants'    => array_sum(array_column($parAnnee, 'manquants')),
                ];
            }

            if ($indicateur['calculable']) {
                $reference = $indicateur['periodes'][0]['valeur'];
                $futur     = $indicateur['periodes'][count($indicateur['periodes']) - 1]['valeur'];

                $indicateur['evolution'] = Indicateurs::evolution($code, $reference, $futur);
            }

            $resultat[$code] = $indicateur;
        }

        return $resultat;
    }
}
