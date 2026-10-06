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
        $start = $this->request->getGet('start_date');
        $end   = $this->request->getGet('end_date');
        $json  = null;
        $error = null;

        // Only call the API once the form has been submitted
        if ($start !== null || $end !== null) {
            $rules = [
                'start_date' => 'required|valid_date[Y-m-d]',
                'end_date'   => 'required|valid_date[Y-m-d]',
            ];

            if (! $this->validateData(['start_date' => $start, 'end_date' => $end], $rules)) {
                $error = implode(' ', $this->validator->getErrors());
            } elseif ($end < $start) {
                $error = 'The end date must not be before the start date.';
            } elseif ((new DateTime($start))->diff(new DateTime($end))->days >= self::MAX_DAYS) {
                $error = 'Please choose a range of at most ' . self::MAX_DAYS . ' days.';
            } else {
                try {
                    // One cache entry per date range, so repeated searches don't hit the API
                    $data = cache()->remember(
                        'climate_' . md5($start . $end),
                        DAY,
                        static fn () => (new ClimateApi())->fetch(43.60, 1.44, $start, $end)
                    );

                    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return view('pages/climate', [
            'start' => $start,
            'end'   => $end,
            'json'  => $json,
            'error' => $error,
        ]);
    }
}