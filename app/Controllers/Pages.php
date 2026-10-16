<?php

namespace App\Controllers;
use CodeIgniter\Exceptions\PageNotFoundException;

use App\Libraries\ClimateApi;
use DateTime;
use RuntimeException;

class Pages extends BaseController
{
    public function view(string $page = 'home')
    {
        if (! is_file(APPPATH . 'Views/pages/' . $page . '.php')) {
            // Whoops, we don't have a page for that!
            throw new PageNotFoundException($page);
        }

        $data['title'] = ucfirst($page); // Capitalize the first letter

        return view('templates/header', $data)
            . view('pages/' . $page)
            . view('templates/footer');
    }

    private const MAX_DAYS = 366; // protects the shared API quota

    public function climate()
    {
        $lat   = $this->request->getGet('latitude');
        $lon   = $this->request->getGet('longitude');
        $start = $this->request->getGet('start_date');
        $end   = $this->request->getGet('end_date');
        $json  = null;
        $error = null;

        $submitted = $lat !== null || $lon !== null || $start !== null || $end !== null;

        if ($submitted) {
            $rules = [
                'latitude'   => 'required|decimal|greater_than_equal_to[-90]|less_than_equal_to[90]',
                'longitude'  => 'required|decimal|greater_than_equal_to[-180]|less_than_equal_to[180]',
                'start_date' => 'required|valid_date[Y-m-d]',
                'end_date'   => 'required|valid_date[Y-m-d]',
            ];

            $input = [
                'latitude'   => $lat,
                'longitude'  => $lon,
                'start_date' => $start,
                'end_date'   => $end,
            ];

            if (! $this->validateData($input, $rules)) {
                $error = implode(' ', $this->validator->getErrors());
            } elseif ($end < $start) {
                $error = 'The end date must not be before the start date.';
            } elseif ((new DateTime($start))->diff(new DateTime($end))->days >= self::MAX_DAYS) {
                $error = 'Please choose a range of at most ' . self::MAX_DAYS . ' days.';
            } else {
                $latF = (float) $lat;
                $lonF = (float) $lon;

                try {
                    // The key includes coordinates, so each location/range is cached separately
                    $data = cache()->remember(
                        'climate_' . md5("{$latF}|{$lonF}|{$start}|{$end}"),
                        DAY,
                        static fn () => (new ClimateApi())->fetch($latF, $lonF, $start, $end)
                    );

                    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return view('pages/climate', [
            'latitude'  => $lat ?? '43.60',   // defaults to Toulouse on first visit
            'longitude' => $lon ?? '1.44',
            'start'     => $start,
            'end'       => $end,
            'json'      => $json,
            'error'     => $error,
        ]);
    }
}