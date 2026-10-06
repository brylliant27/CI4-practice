<?php

namespace App\Controllers;

use App\Libraries\ClimateApi;
use RuntimeException;

class Climate extends BaseController
{
    public function index()
    {
        try {
            // Cached for a day, so refreshing the page doesn't eat the shared quota
            $data = cache()->remember('climate_toulouse_2049', DAY, static function () {
                return (new ClimateApi())->fetch(43.60, 1.44, '2049-01-01', '2049-12-31');
            });
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(502)->setJSON(['error' => $e->getMessage()]);
        }

        return $this->response->setJSON([
            'dates' => array_slice($data['daily']['time'], 0, 5),
            't_max' => array_slice($data['daily']['temperature_2m_max'], 0, 5),
        ]);
    }
}