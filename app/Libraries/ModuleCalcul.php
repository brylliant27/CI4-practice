<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Appelle le module de calcul du pôle DATA (calcul/module_calcul.py).
 *
 *   PHP ──(JSON sur l'entrée standard : culture + séries du contrat v1.0)──► Python
 *   PHP ◄──(une ligne JSON sur la sortie standard : indicateurs calculés)──── Python
 *
 * Le module Python ne touche ni à la base ni à l'API : le pôle DEV fournit les séries,
 * le pôle DATA renvoie les valeurs, et ServiceDiagnostic les enregistre dans fact_indicateur.
 * Format détaillé : calcul/README.md.
 *
 * Réglages (.env) :
 *   calcul.python = python3     commande Python (python sous Windows, python3 sur Alwaysdata)
 */
class ModuleCalcul
{
    /** Au-delà, on abandonne : le calcul de 2 × 10 ans prend moins d'une seconde. */
    private const DELAI_MAX_S = 30;

    private string $python;
    private string $script;

    public function __construct(?string $python = null, ?string $script = null)
    {
        $this->python = $python ?? (string) env('calcul.python', PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
        $this->script = $script ?? ROOTPATH . 'calcul' . DIRECTORY_SEPARATOR . 'module_calcul.py';
    }

    /**
     * @param array<string, mixed> $culture  ligne de dim_culture
     * @param list<array{id_temps: int, annee_debut: int, annee_fin: int, series: list<array<string, mixed>>}> $periodes
     *
     * @return array<string, array<string, mixed>> par code d'indicateur :
     *     libelle, unite, calculable, message, periodes[id_temps] = {moyenne, annees[annee] = {valeur, manquants}}
     *
     * @throws RuntimeException module introuvable, trop long, ou réponse inexploitable
     */
    public function calculer(array $culture, array $periodes): array
    {
        $entree  = json_encode(['culture' => $culture, 'periodes' => $periodes], JSON_THROW_ON_ERROR);
        $reponse = $this->executer($entree);

        if (($reponse['statut'] ?? null) !== 'ok' || ! is_array($reponse['indicateurs'] ?? null)) {
            throw new RuntimeException('Module de calcul : ' . ($reponse['message'] ?? 'réponse inattendue.'));
        }

        return $reponse['indicateurs'];
    }

    /** @return array<string, mixed> la réponse JSON décodée */
    private function executer(string $entree): array
    {
        if (! is_file($this->script)) {
            throw new RuntimeException("Module de calcul introuvable : {$this->script}");
        }

        // Tableau de commande : pas de shell, donc pas d'injection possible par les arguments.
        $processus = proc_open(
            [$this->python, $this->script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tubes
        );
        if (! is_resource($processus)) {
            throw new RuntimeException("Impossible de lancer « {$this->python} ».");
        }

        // Le module lit TOUTE l'entrée avant d'écrire quoi que ce soit : on peut écrire d'un bloc.
        fwrite($tubes[0], $entree);
        fclose($tubes[0]);

        [$sortie, $erreurs, $delaiDepasse] = $this->lireAvecDelai($tubes[1], $tubes[2]);
        fclose($tubes[1]);
        fclose($tubes[2]);

        if ($delaiDepasse) {
            proc_terminate($processus);
            proc_close($processus);

            throw new RuntimeException('Module de calcul : délai de ' . self::DELAI_MAX_S . ' s dépassé.');
        }
        $code = proc_close($processus);

        $reponse = json_decode(trim($sortie), true);
        if (! is_array($reponse)) {
            // Python absent, erreur de syntaxe, print() parasite... : stderr dit pourquoi
            log_message('error', "Module de calcul (code {$code}) : {$erreurs}");

            throw new RuntimeException("Module de calcul : réponse illisible (code {$code}). " . mb_substr(trim($erreurs), -300));
        }
        if (trim($erreurs) !== '') {
            log_message('warning', "Module de calcul (stderr) : {$erreurs}");
        }

        return $reponse;
    }

    /**
     * Lit stdout et stderr en même temps (sinon un tube plein bloquerait les deux processus).
     *
     * @param resource $stdout
     * @param resource $stderr
     *
     * @return array{0: string, 1: string, 2: bool} sortie, erreurs, délai dépassé
     */
    private function lireAvecDelai($stdout, $stderr): array
    {
        $tampons = [(int) $stdout => '', (int) $stderr => ''];
        $ouverts = [$stdout, $stderr];
        $limite  = microtime(true) + self::DELAI_MAX_S;

        while ($ouverts !== []) {
            $reste = $limite - microtime(true);
            if ($reste <= 0) {
                return [$tampons[(int) $stdout], $tampons[(int) $stderr], true];
            }
            $lecture = $ouverts;
            $rien    = null;
            if (stream_select($lecture, $rien, $rien, (int) $reste, 200_000) === false) {
                break;
            }
            foreach ($lecture as $tube) {
                $morceau = fread($tube, 65536);
                if ($morceau === '' || $morceau === false) {
                    if (feof($tube)) {
                        $ouverts = array_values(array_filter($ouverts, static fn ($t) => $t !== $tube));
                    }

                    continue;
                }
                $tampons[(int) $tube] .= $morceau;
            }
        }

        return [$tampons[(int) $stdout], $tampons[(int) $stderr], false];
    }
}
