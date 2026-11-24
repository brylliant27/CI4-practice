<?php

namespace App\Models;

use CodeIgniter\Model;

/** Dim_Temps : les horizons comparés (référence récente, horizon 2050). */
class TempsModel extends Model
{
    protected $table         = 'dim_temps';
    protected $primaryKey    = 'id_temps';
    protected $returnType    = 'array';
    protected $allowedFields = ['id_temps', 'libelle', 'annee_debut', 'annee_fin', 'type_periode'];
    protected array $casts   = [
        'id_temps'    => 'int',
        'annee_debut' => 'int',
        'annee_fin'   => 'int',
    ];

    /** @return list<array<string, mixed>> la référence d'abord, puis les projections */
    public function horizons(): array
    {
        return $this->orderBy('annee_debut')->findAll();
    }
}
