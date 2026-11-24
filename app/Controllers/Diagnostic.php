<?php

namespace App\Controllers;

use App\Libraries\ServiceDiagnostic;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

/** Résultat du diagnostic : /diagnostic?commune=13&culture=4 */
class Diagnostic extends BaseController
{
    public function index(): ResponseInterface|string
    {
        $entrees = [
            'commune' => $this->request->getGet('commune'),
            'culture' => $this->request->getGet('culture'),
        ];

        // Les identifiants viennent de l'URL, que n'importe qui peut modifier :
        // on n'accepte que des entiers positifs (et le Query Builder échappe le reste).
        if (! $this->validateData($entrees, [
            'commune' => 'required|is_natural_no_zero',
            'culture' => 'required|is_natural_no_zero',
        ])) {
            return $this->pageErreur(400, 'Choisissez une commune et une culture dans la liste.');
        }

        try {
            $resultat = (new ServiceDiagnostic())->executer(
                $this->idVisiteur(),
                (int) $entrees['commune'],
                (int) $entrees['culture']
            );
        } catch (InvalidArgumentException $e) {
            return $this->pageErreur(404, $e->getMessage());
        } catch (RuntimeException $e) {
            log_message('error', 'Diagnostic impossible : ' . $e->getMessage());

            return $this->pageErreur(502, 'Le diagnostic n\'a pas pu être calculé. ' . $e->getMessage()
                . ' Réessayez dans quelques instants.');
        }

        return view('diagnostic', $resultat);
    }
}
