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
    // latitude / longitude restent en texte (le cast float de CI4 refuse les entiers
    // renvoyés par SQLite) : elles sont converties en nombres à l'appel de l'API.
    protected array $casts = [
        'id_commune' => 'int',
        'altitude_m' => '?int',
    ];

    /** @return list<array<string, mixed>> */
    public function pourFormulaire(): array
    {
        return $this->select('id_commune, nom, zone_climatique')->orderBy('nom')->findAll();
    }
}
