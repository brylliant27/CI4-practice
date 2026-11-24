<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);
    }

    /**
     * Identifiant anonyme du visiteur (id_session de fact_indicateur), créé au
     * premier diagnostic et conservé dans la session côté serveur. Ce n'est pas
     * l'identifiant du cookie lui-même : un cookie volé ne le révèle pas.
     */
    protected function idVisiteur(): string
    {
        $id = session('id_visiteur');
        if (! is_string($id) || preg_match('/^[0-9a-f]{32}$/', $id) !== 1) {
            $id = bin2hex(random_bytes(16));
            session()->set('id_visiteur', $id);
        }

        return $id;
    }

    /** L'identifiant du visiteur s'il en a déjà un, sans en créer. */
    protected function idVisiteurExistant(): ?string
    {
        $id = session('id_visiteur');

        return is_string($id) ? $id : null;
    }

    protected function pageErreur(int $statut, string $message): ResponseInterface
    {
        return $this->response
            ->setStatusCode($statut)
            ->setBody(view('erreur', ['statut' => $statut, 'message' => $message]));
    }
}
