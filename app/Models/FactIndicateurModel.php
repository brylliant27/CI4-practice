<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Fact_Indicateur : la table de faits, au centre de l'étoile.
 *
 * Toute lecture et toute écriture sont FILTRÉES sur id_session : un visiteur ne voit
 * et n'efface que ses propres lignes (isolation par session, cf. Conception RGPD).
 * La clé primaire est composée de 6 colonnes : on passe donc par le Query Builder.
 */
class FactIndicateurModel extends Model
{
    protected $table         = 'fact_indicateur';
    protected $primaryKey    = 'id_session';
    protected $returnType    = 'array';
    protected $allowedFields = ['id_session', 'id_commune', 'id_culture', 'id_temps', 'id_modele', 'code_indicateur', 'valeur', 'date_calcul'];

    /**
     * Remplace les faits de CETTE session pour CETTE sélection (commune × culture × modèle).
     *
     * @param list<array{id_temps: int, code_indicateur: string, valeur: float|null}> $faits
     */
    public function remplacer(string $idSession, int $idCommune, int $idCulture, int $idModele, array $faits): void
    {
        $maintenant = date('Y-m-d H:i:s');
        $lignes     = array_map(static fn (array $f) => [
            'id_session'      => $idSession,
            'id_commune'      => $idCommune,
            'id_culture'      => $idCulture,
            'id_temps'        => $f['id_temps'],
            'id_modele'       => $idModele,
            'code_indicateur' => $f['code_indicateur'],
            'valeur'          => $f['valeur'],
            'date_calcul'     => $maintenant,
        ], $faits);

        // Suppression + réinsertion dans une transaction : jamais d'état à moitié écrit.
        $this->db->transException(true)->transStart();
        $this->builder()
            ->where('id_session', $idSession)
            ->where('id_commune', $idCommune)
            ->where('id_culture', $idCulture)
            ->where('id_modele', $idModele)
            ->delete();
        if ($lignes !== []) {
            $this->builder()->insertBatch($lignes);
        }
        $this->db->transComplete();
    }

    /**
     * Relit les faits d'une sélection, joints à la dimension Temps (requête en étoile).
     *
     * @return list<array<string, mixed>>
     */
    public function lire(string $idSession, int $idCommune, int $idCulture, int $idModele): array
    {
        return $this->db->table('fact_indicateur fi')
            ->select('fi.code_indicateur, fi.valeur, t.id_temps, t.libelle, t.annee_debut, t.annee_fin, t.type_periode')
            ->join('dim_temps t', 't.id_temps = fi.id_temps')
            ->where('fi.id_session', $idSession)
            ->where('fi.id_commune', $idCommune)
            ->where('fi.id_culture', $idCulture)
            ->where('fi.id_modele', $idModele)
            ->orderBy('t.annee_debut')
            ->get()->getResultArray();
    }

    /**
     * Toutes les données d'une session, en clair (droit d'accès et portabilité).
     *
     * @return list<array<string, mixed>>
     */
    public function exporterSession(string $idSession): array
    {
        return $this->db->table('fact_indicateur fi')
            ->select('c.nom AS commune, cu.nom AS culture, t.libelle AS horizon, t.annee_debut, t.annee_fin, '
                . 'm.code AS modele, fi.code_indicateur, fi.valeur, fi.date_calcul')
            ->join('dim_commune c', 'c.id_commune = fi.id_commune')
            ->join('dim_culture cu', 'cu.id_culture = fi.id_culture')
            ->join('dim_temps t', 't.id_temps = fi.id_temps')
            ->join('dim_modele m', 'm.id_modele = fi.id_modele')
            ->where('fi.id_session', $idSession)
            ->orderBy('fi.date_calcul')
            ->get()->getResultArray();
    }

    /** Droit à l'effacement : supprime UNIQUEMENT les lignes de cette session. */
    public function effacerSession(string $idSession): int
    {
        $this->builder()->where('id_session', $idSession)->delete();

        return $this->db->affectedRows();
    }

    /** Purge RGPD : supprime les résultats calculés avant la date donnée. */
    public function purgerAvant(string $dateLimite): int
    {
        $this->builder()->where('date_calcul <', $dateLimite)->delete();

        return $this->db->affectedRows();
    }
}
