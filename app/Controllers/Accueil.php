<?php

namespace App\Controllers;

use App\Models\CommuneModel;
use App\Models\CultureModel;

/** Page d'accueil : choix d'une commune et d'une culture. */
class Accueil extends BaseController
{
    public function index(): string
    {
        $efface = $this->request->getGet('efface');

        return view('accueil', [
            'communes' => model(CommuneModel::class)->pourFormulaire(),
            'cultures' => model(CultureModel::class)->pourFormulaire(),
            'efface'   => ctype_digit((string) $efface) ? (int) $efface : null,
        ]);
    }
}
