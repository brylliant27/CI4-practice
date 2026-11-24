<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Client de l'API Open-Meteo Climate (pôle DEV).
 *
 * Toujours appelé CÔTÉ SERVEUR, jamais depuis le navigateur. Il :
 *  - demande les 5 variables journalières du contrat d'interface ;
 *  - met chaque réponse en cache 30 jours (les projections ne changent pas, et le
 *    quota gratuit est partagé par toute l'IUT) ;
 *  - réessaie deux fois si l'API répond 429 (trop de requêtes) ;
 *  - utilise la clé de la promo si OPENMETEO_API_KEY est définie dans .env
 *    (jamais dans le code), et revient à l'API gratuite si la clé est refusée.
 *
 * Open-Meteo applique par défaut une correction de biais : on ne la désactive pas.
 */
class ClimateApi
{
    public const URL           = 'https://climate-api.open-meteo.com/v1/climate';
    public const MODELE_DEFAUT = 'MRI_AGCM3_2_S';

    /** Variables journalières demandées : celles du contrat d'interface v1.0. */
    public const VARIABLES = [
        'temperature_2m_min',
        'temperature_2m_max',
        'temperature_2m_mean',
        'precipitation_sum',
        'et0_fao_evapotranspiration_sum',
    ];

    private const DUREE_CACHE  = 30 * DAY;
    private const ATTENTES_429 = [2, 5]; // secondes d'attente avant chaque nouvel essai

    /**
     * @return array<string, mixed> la réponse JSON décodée (avec son bloc "daily")
     *
     * @throws RuntimeException si l'API ne répond pas correctement
     */
    public function fetch(
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate,
        string $model = self::MODELE_DEFAUT
    ): array {
        $params = [
            'latitude'   => $latitude,
            'longitude'  => $longitude,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'models'     => $model,
            'daily'      => implode(',', self::VARIABLES),
            'timezone'   => 'Europe/Paris',
        ];

        // La clé API n'entre pas dans la clé du cache : la réponse est la même.
        $cleCache = 'openmeteo_' . md5(json_encode($params));
        $enCache  = cache($cleCache);
        if (is_array($enCache)) {
            return $enCache;
        }

        $data = $this->appeler($params);
        cache()->save($cleCache, $data, self::DUREE_CACHE);

        return $data;
    }

    /** @return array<string, mixed> */
    protected function appeler(array $params): array
    {
        $cleApi  = env('OPENMETEO_API_KEY') ?: null;
        $essais  = [0, ...self::ATTENTES_429];
        $dernier = '';

        while ($essais !== []) {
            $attente = array_shift($essais);
            if ($attente > 0) {
                sleep($attente);
            }

            // Avec la clé : serveurs réservés « customer- », sans limite par adresse IP.
            $url   = $cleApi ? str_replace('https://', 'https://customer-', self::URL) : self::URL;
            $query = $cleApi ? $params + ['apikey' => $cleApi] : $params;

            $response = single_service('curlrequest', [
                'timeout'         => 30,
                'connect_timeout' => 10,
                'http_errors'     => false,
                'verify'          => env('CURL_CA_BUNDLE', true),
            ])->get($url, ['query' => $query]);

            $code  = $response->getStatusCode();
            $corps = (string) $response->getBody();

            if ($code === 200) {
                $data = json_decode($corps, true);
                if (! is_array($data) || ! isset($data['daily']['time'])) {
                    throw new RuntimeException('Réponse inattendue de l\'API Open-Meteo (pas de bloc « daily »).');
                }

                return $data;
            }

            $raison  = json_decode($corps, true)['reason'] ?? substr($corps, 0, 200);
            $dernier = "HTTP {$code} : {$raison}";

            if ($code === 429) {
                continue; // quota momentanément dépassé : on patiente puis on réessaie
            }

            if ($cleApi !== null && in_array($code, [400, 401, 403], true) && stripos($raison, 'api key') !== false) {
                log_message('warning', 'Clé Open-Meteo refusée : retour à l\'API gratuite.');
                $cleApi = null;
                array_unshift($essais, 0); // nouvel essai immédiat, sans la clé

                continue;
            }

            break; // autre erreur (paramètres, panne) : inutile d'insister
        }

        throw new RuntimeException("L'API Open-Meteo n'a pas répondu correctement ({$dernier}).");
    }
}
