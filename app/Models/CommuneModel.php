<?php

namespace App\Models;

use CodeIgniter\Model;

/** Dim_Commune : communes proposées à l'utilisateur (référentiel). */
class CommuneModel extends Model
{
    protected $table         = 'dim_commune';
    protected $primaryKey    = 'id_commune';
    protected $returnType    = 'array';
    protected $allowedFields = ['id_commune', 'nom', 'code_insee', 'latitude', 'longitude', 'altitude_m', 'zone_climatique'];
    protected array $casts   = [
        'id_commune' => 'int',
        'latitude'   => 'float',
        'longitude'  => 'float',
        'altitude_m' => '?int',
    ];

    /** @return list<array<string, mixed>> */
    public function pourFormulaire(): array
    {
        return $this->select('id_commune, nom, zone_climatique')->orderBy('nom')->findAll();
    }
}
