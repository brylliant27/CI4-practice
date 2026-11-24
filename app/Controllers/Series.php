<?php

namespace App\Controllers;

use App\Libraries\ClimateApi;
use App\Libraries\SeriesClimat;
use App\Models\CommuneModel;
use CodeIgniter\HTTP\ResponseInterface;
use DateTime;
use RuntimeException;

/**
 * Outil du pôle DEV : extraire un échantillon de séries AU FORMAT DU CONTRAT
 * D'INTERFACE (docs/contrat-interface.md), pour que le pôle DATA teste ses
 * indicateurs sur des données réelles.
 *
 *   /series                       formulaire + aperçu
 *   /series/csv?commune=13&debut=2015-01-01&fin=2015-12-31
 *   /series/json?...              même chose, avec les métadonnées
 */
class Series extends BaseController
{
    private const MAX_JOURS = 3653; // 10 ans, années bissextiles comprises

    public function index(): string
    {
        $entrees = $this->lireEntrees();
        $vue     = [
            'communes' => model(CommuneModel::class)->pourFormulaire(),
            'entrees'  => $entrees,
            'erreur'   => null,
            'commune'  => null,
            'series'   => null,
            'requete'  => null,
        ];

        if (array_filter($entrees, static fn ($v) => $v !== null && $v !== '') !== []) {
            [$vue['series'], $vue['commune'], $vue['erreur']] = $this->charger($entrees);
            $vue['requete'] = http_build_query($entrees);
        }

        return view('series', $vue);
    }

    public function csv(): ResponseInterface
    {
        $entrees                     = $this->lireEntrees();
        [$series, $commune, $erreur] = $this->charger($entrees);
        if ($series === null) {
            return $this->response->setStatusCode(400)->setContentType('text/plain')->setBody($erreur);
        }

        return $this->response->download($this->nomFichier($entrees, 'csv'), SeriesClimat::versCsv($series), true);
    }

    public function json(): ResponseInterface
    {
        $entrees                     = $this->lireEntrees();
        [$series, $commune, $erreur] = $this->charger($entrees);
        if ($series === null) {
            return $this->response->setStatusCode(400)->setContentType('text/plain')->setBody($erreur);
        }

        $document = [
            'contrat' => [
                'version'       => SeriesClimat::VERSION_CONTRAT,
                'date'          => SeriesClimat::DATE_CONTRAT,
                'documentation' => 'docs/contrat-interface.md',
            ],
            'source' => [
                'api'        => 'Open-Meteo Climate API',
                'url'        => ClimateApi::URL,
                'modele'     => ClimateApi::MODELE_DEFAUT,
                'extrait_le' => date('Y-m-d'),
            ],
            'commune' => [
                'id_commune' => $commune['id_commune'],
                'nom'        => $commune['nom'],
                'code_insee' => $commune['code_insee'],
                'latitude'   => $commune['latitude'],
                'longitude'  => $commune['longitude'],
            ],
            'unites' => SeriesClimat::UNITES,
            'series' => $series,
        ];

        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        return $this->response->download($this->nomFichier($entrees, 'json'), $json, true);
    }

    /** @return array{commune: ?string, debut: ?string, fin: ?string} */
    private function lireEntrees(): array
    {
        return [
            'commune' => $this->request->getGet('commune'),
            'debut'   => $this->request->getGet('debut'),
            'fin'     => $this->request->getGet('fin'),
        ];
    }

    /** @return array{0: ?list<array<string, mixed>>, 1: ?array<string, mixed>, 2: ?string} [séries, commune, erreur] */
    private function charger(array $entrees): array
    {
        $regles = [
            'commune' => ['rules' => 'required|is_natural_no_zero', 'errors' => [
                'required' => 'Choisissez une commune.', 'is_natural_no_zero' => 'Commune invalide.']],
            'debut' => ['rules' => 'required|valid_date[Y-m-d]', 'errors' => [
                'required' => 'Indiquez une date de début.', 'valid_date' => 'Date de début invalide (AAAA-MM-JJ).']],
            'fin' => ['rules' => 'required|valid_date[Y-m-d]', 'errors' => [
                'required' => 'Indiquez une date de fin.', 'valid_date' => 'Date de fin invalide (AAAA-MM-JJ).']],
        ];
        if (! $this->validateData($entrees, $regles)) {
            return [null, null, implode(' ', $this->validator->getErrors())];
        }

        [$debut, $fin] = [$entrees['debut'], $entrees['fin']];
        if ($fin < $debut) {
            return [null, null, 'La date de fin doit être après la date de début.'];
        }
        if ($debut < '1950-01-01' || $fin > '2050-12-31') {
            return [null, null, 'Open-Meteo Climate couvre la période 1950-2050.'];
        }
        if ((new DateTime($debut))->diff(new DateTime($fin))->days >= self::MAX_JOURS) {
            return [null, null, 'Choisissez une période de 10 ans au plus.'];
        }

        $commune = model(CommuneModel::class)->find((int) $entrees['commune']);
        if ($commune === null) {
            return [null, null, 'Commune inconnue.'];
        }

        try {
            $reponse = service('climateApi')->fetch((float) $commune['latitude'], (float) $commune['longitude'], $debut, $fin);
        } catch (RuntimeException $e) {
            return [null, $commune, $e->getMessage()];
        }

        return [SeriesClimat::depuisApi($commune['id_commune'], $reponse), $commune, null];
    }

    private function nomFichier(array $entrees, string $extension): string
    {
        return sprintf('series_commune%d_%s_%s.%s', (int) $entrees['commune'], $entrees['debut'], $entrees['fin'], $extension);
    }
}
