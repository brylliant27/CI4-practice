<?php

namespace App\Models;

use CodeIgniter\Model;

/** Dim_Modele : modèles climatiques interrogés sur Open-Meteo. */
class ModeleModel extends Model
{
    protected $table         = 'dim_modele';
    protected $primaryKey    = 'id_modele';
    protected $returnType    = 'array';
    protected $allowedFields = ['id_modele', 'code', 'libelle', 'institut', 'resolution_km'];
    protected array $casts   = [
        'id_modele'     => 'int',
        'resolution_km' => '?int',
    ];

    public function parCode(string $code): ?array
    {
        return $this->where('code', $code)->first();
    }
}
