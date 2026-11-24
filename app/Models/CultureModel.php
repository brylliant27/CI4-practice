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

    // Les seuils peuvent être absents (non sourcés) : on garde null plutôt que 0.
    protected array $casts = [
        'id_culture'               => 'int',
        't_base_c'                 => '?float',
        'gdd_maturite_cj'          => '?float',
        'seuil_gel_c'              => '?float',
        'seuil_stress_thermique_c' => '?float',
        't_opt_min_c'              => '?float',
        't_opt_max_c'              => '?float',
        'besoin_eau_mm'            => '?float',
    ];

    /** @return list<array<string, mixed>> */
    public function pourFormulaire(): array
    {
        return $this->select('id_culture, nom')->orderBy('nom')->findAll();
    }
}
