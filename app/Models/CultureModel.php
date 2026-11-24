<?php

namespace App\Models;

use CodeIgniter\Model;

/** Dim_Culture : seuils physiologiques des cultures (référentiel du pôle DATA). */
class CultureModel extends Model
{
    protected $table         = 'dim_culture';
    protected $primaryKey    = 'id_culture';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'id_culture', 'nom', 't_base_c', 'gdd_maturite_cj', 'seuil_gel_c', 'seuil_stress_thermique_c',
        't_opt_min_c', 't_opt_max_c', 'besoin_eau_mm', 'source',
    ];

    // Pas de cast « float » sur les seuils : MariaDB les renvoie en texte ("0.00") et
    // SQLite en entier (0), ce que le cast float de CI4 refuse. Les seuils absents
    // (non sourcés) restent null ; Indicateurs les convertit lui-même en nombres.
    protected array $casts = [
        'id_culture' => 'int',
    ];

    /** @return list<array<string, mixed>> */
    public function pourFormulaire(): array
    {
        return $this->select('id_culture, nom')->orderBy('nom')->findAll();
    }
}
