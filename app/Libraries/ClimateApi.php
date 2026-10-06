<?php

namespace App\Libraries;

use RuntimeException;

class ClimateApi
{
    private const URL = 'https://climate-api.open-meteo.com/v1/climate';

    public function fetch(
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate,
        string $model = 'MRI_AGCM3_2_S'
    ): array {
        $client = service('curlrequest', [
            'timeout'     => 15,
            'http_errors' => false, // we handle status codes ourselves
        ]);

        $response = $client->get(self::URL, [
            'query' => [
                'latitude'   => $latitude,
                'longitude'  => $longitude,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'models'     => $model,
                'daily'      => 'temperature_2m_max,temperature_2m_min,precipitation_sum',
                'timezone'   => 'Europe/Paris',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(
                'API error ' . $response->getStatusCode() . ': ' . $response->getBody()
            );
        }

        $data = json_decode($response->getBody(), true);

        if (! isset($data['daily'])) {
            throw new RuntimeException('Unexpected API response.');
        }

        return $data;
    }
}