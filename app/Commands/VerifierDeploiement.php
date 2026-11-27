<?php

namespace App\Commands;

use App\Libraries\ClimateApi;
use App\Libraries\ModuleCalcul;
use App\Libraries\SeriesClimat;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Vérifie en une commande que le serveur peut faire tourner l'application :
 *
 *     php spark deploiement:verifier
 *
 * À lancer en SSH sur Alwaysdata après chaque déploiement (voir docs/deploiement.md).
 * Chaque ligne teste UNE chose : en cas d'échec, on sait tout de suite où chercher.
 */
class VerifierDeploiement extends BaseCommand
{
    protected $group       = 'Équipe 04';
    protected $name        = 'deploiement:verifier';
    protected $description = 'Vérifie PHP, Python, le module de calcul, la base et l\'API Open-Meteo.';

    private int $echecs = 0;

    public function run(array $params)
    {
        CLI::write('Vérification du déploiement (environnement : ' . ENVIRONMENT . ')', 'yellow');

        $this->verifier('PHP ≥ 8.2', static fn () => version_compare(PHP_VERSION, '8.2', '>=') ? PHP_VERSION : false);
        foreach (['intl', 'mbstring', 'curl', 'mysqli'] as $ext) {
            $this->verifier("Extension PHP {$ext}", static fn () => extension_loaded($ext));
        }
        $this->verifier('Dossier writable/ accessible en écriture', static fn () => is_writable(WRITEPATH));
        $this->verifier('proc_open autorisé (appel du module Python)', static fn () => function_exists('proc_open')
            && ! in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true));

        $this->verifier('Module de calcul DATA (jeu d\'essais)', static function () {
            $series = SeriesClimat::lireCsv(ROOTPATH . 'tests/_support/Data/jeu-essais-series.csv');
            $res    = (new ModuleCalcul())->calculer(
                ['nom' => 'test', 'seuil_gel_c' => 0, 'seuil_stress_thermique_c' => 35, 't_base_c' => 6],
                [['id_temps' => 1, 'annee_debut' => 2011, 'annee_fin' => 2020, 'series' => $series]]
            );
            $gel = $res['jours_gel']['periodes']['1']['moyenne'] ?? null;

            // Résultat attendu, calculé à la main : voir calcul/test_module_calcul.py
            return $gel === 1.0 || $gel === 1 ? count($res) . ' indicateurs, jours de gel = 1,0 (attendu)' : false;
        });

        $this->verifier('Base de données : référentiels peuplés', static function () {
            $db     = db_connect();
            $compte = [];
            foreach (['dim_commune', 'dim_culture', 'dim_temps', 'dim_modele'] as $table) {
                $compte[] = $table . ' = ' . $db->table($table)->countAllResults();
            }
            $db->table('fact_indicateur')->countAllResults(); // la table de faits doit exister

            return implode(', ', $compte);
        });

        $this->verifier('API Open-Meteo joignable depuis le serveur', static function () {
            $reponse = (new ClimateApi())->fetch(43.6, 1.44, '2015-01-01', '2015-01-07');

            return count($reponse['daily']['time'] ?? []) === 7 ? '7 jours reçus' : false;
        });

        CLI::newLine();
        if ($this->echecs > 0) {
            CLI::error("{$this->echecs} vérification(s) en échec : voir docs/deploiement.md, « Quand ça ne marche pas ».");

            return EXIT_ERROR;
        }
        CLI::write('Tout est prêt.', 'green');

        return EXIT_SUCCESS;
    }

    /** @param callable(): (bool|string) $test vrai, ou un détail à afficher ; faux = échec */
    private function verifier(string $libelle, callable $test): void
    {
        try {
            $resultat = $test();
        } catch (Throwable $e) {
            $resultat = false;
            $libelle .= ' — ' . $e->getMessage();
        }

        if ($resultat === false) {
            $this->echecs++;
            CLI::write('  ✗ ' . $libelle, 'red');

            return;
        }
        CLI::write('  ✓ ' . $libelle . (is_string($resultat) ? " ({$resultat})" : ''), 'green');
    }
}
