<?php

namespace App\Controllers;

use App\Models\FactIndicateurModel;
use CodeIgniter\HTTP\ResponseInterface;

/** Droits RGPD du visiteur : accès / portabilité (CSV) et effacement. */
class MesDonnees extends BaseController
{
    /** « Télécharger mes données » : tout ce qui est rattaché à son identifiant. */
    public function telecharger(): ResponseInterface
    {
        $id     = $this->idVisiteurExistant();
        $lignes = $id === null ? [] : model(FactIndicateurModel::class)->exporterSession($id);

        $flux = fopen('php://temp', 'r+');
        fputcsv($flux, ['commune', 'culture', 'horizon', 'annee_debut', 'annee_fin', 'modele', 'code_indicateur', 'valeur', 'date_calcul'], ',', '"', '');
        foreach ($lignes as $ligne) {
            fputcsv($flux, array_values($ligne), ',', '"', '');
        }
        rewind($flux);
        $csv = stream_get_contents($flux);
        fclose($flux);

        return $this->response->download('mes-donnees-climat-cultures.csv', $csv, true);
    }

    /**
     * « Effacer mes données », en deux temps (comme le kit) :
     *  1. on supprime les lignes de CE visiteur, et uniquement les siennes ;
     *  2. on détruit sa session : au prochain passage il reçoit un nouvel
     *     identifiant, sans lien avec l'ancien.
     */
    public function effacer(): ResponseInterface
    {
        $id      = $this->idVisiteurExistant();
        $effaces = $id === null ? 0 : model(FactIndicateurModel::class)->effacerSession($id);

        session()->destroy();

        return redirect()->to(site_url('/') . '?efface=' . $effaces);
    }
}
