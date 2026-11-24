<?php

namespace Tests\Support;

use App\Libraries\ClimateApi;
use DateTime;

/**
 * Fausse API Open-Meteo pour les tests : aucun appel réseau, résultat connu d'avance.
 *
 * Chaque année : 3 jours de gel (1er-3 janvier, -1 °C) jusqu'en 2020, puis 1 seul
 * (1er janvier) à partir de 2021. Le reste du temps : 5 °C. Donc, pour une culture
 * qui gèle à 0 °C : référence 3,0 jours/an, horizon 2050 1,0 jour/an.
 */
final class FauxClimateApi extends ClimateApi
{
    public int $appels = 0;

    public function fetch(
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate,
        string $model = self::MODELE_DEFAUT
    ): array {
        $this->appels++;
        $daily = ['time' => []];
        $fin   = new DateTime($endDate);

        for ($jour = new DateTime($startDate); $jour <= $fin; $jour->modify('+1 day')) {
            $joursDeGel = (int) $jour->format('Y') <= 2020 ? 3 : 1;
            $gel        = $jour->format('m') === '01' && (int) $jour->format('d') <= $joursDeGel;

            $daily['time'][]                           = $jour->format('Y-m-d');
            $daily['temperature_2m_min'][]             = $gel ? -1.0 : 5.0;
            $daily['temperature_2m_max'][]             = 20.0;
            $daily['temperature_2m_mean'][]            = 10.0;
            $daily['precipitation_sum'][]              = 1.0;
            $daily['et0_fao_evapotranspiration_sum'][] = 2.0;
        }

        return ['daily' => $daily];
    }
}
