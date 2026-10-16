<?php

namespace App\Controllers;

use App\Libraries\ClimateApi;
use DateTime;
use RuntimeException;

class Climate extends BaseController
{
    private const MAX_DAYS  = 366;
    private const DELIMITER = ','; // use ';' if your Excel is in French locale

    public function climate()
    {
        $in     = $this->readInput();
        $json    = null;
        $error   = null;
        $csvUrl  = null;
        $jsonUrl = null;

        if (array_filter($in, static fn ($v) => $v !== null)) {
            [$data, $error] = $this->loadData($in);

            if ($data !== null) {
                $json    = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $query   = '?' . http_build_query($in);
                $csvUrl  = site_url('climate/csv') . $query;
                $jsonUrl = site_url('climate/json') . $query;
            }
        }

        return view('climate', [
            'latitude'  => $in['latitude'] ?? '43.60',
            'longitude' => $in['longitude'] ?? '1.44',
            'start'     => $in['start_date'],
            'end'       => $in['end_date'],
            'json'      => $json,
            'error'     => $error,
            'csvUrl'    => $csvUrl,
            'jsonUrl'   => $jsonUrl,
        ]);
    }

    public function csv()
    {
        $in = $this->readInput();
        [$data, $error] = $this->loadData($in);

        if ($data === null) {
            return $this->response
                ->setStatusCode(400)
                ->setContentType('text/plain')
                ->setBody($error ?? 'Missing parameters.');
        }

        // Column headers = keys of daily_units ("time", "temperature_2m_max", ...)
        $columns = array_keys($data['daily_units']);
        $daily   = $data['daily'];
        $rows    = count($daily['time']);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, $columns, self::DELIMITER, '"', '');

        for ($i = 0; $i < $rows; $i++) {
            $row = [];
            foreach ($columns as $column) {
                $row[] = $daily[$column][$i] ?? '';
            }
            fputcsv($out, $row, self::DELIMITER, '"', '');
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response->download($this->filename($in, 'csv'), $csv, true);
    }

    public function jsonDownload()
    {
        $in = $this->readInput();
        [$data, $error] = $this->loadData($in);

        if ($data === null) {
            return $this->response
                ->setStatusCode(400)
                ->setContentType('text/plain')
                ->setBody($error ?? 'Missing parameters.');
        }

        $json = json_encode(
            $this->toRecords($data),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return $this->response->download($this->filename($in, 'json'), $json, true);
    }

    private function readInput(): array
    {
        return [
            'latitude'   => $this->request->getGet('latitude'),
            'longitude'  => $this->request->getGet('longitude'),
            'start_date' => $this->request->getGet('start_date'),
            'end_date'   => $this->request->getGet('end_date'),
        ];
    }

    /** @return array{0: ?array, 1: ?string} [data, error] */
    private function loadData(array $in): array
    {
        $rules = [
            'latitude'   => 'required|decimal|greater_than_equal_to[-90]|less_than_equal_to[90]',
            'longitude'  => 'required|decimal|greater_than_equal_to[-180]|less_than_equal_to[180]',
            'start_date' => 'required|valid_date[Y-m-d]',
            'end_date'   => 'required|valid_date[Y-m-d]',
        ];

        if (! $this->validateData($in, $rules)) {
            return [null, implode(' ', $this->validator->getErrors())];
        }

        $start = $in['start_date'];
        $end   = $in['end_date'];

        if ($end < $start) {
            return [null, 'The end date must not be before the start date.'];
        }

        if ((new DateTime($start))->diff(new DateTime($end))->days >= self::MAX_DAYS) {
            return [null, 'Please choose a range of at most ' . self::MAX_DAYS . ' days.'];
        }

        $lat = (float) $in['latitude'];
        $lon = (float) $in['longitude'];

        try {
            $data = cache()->remember(
                'climate_' . md5("{$lat}|{$lon}|{$start}|{$end}"),
                DAY,
                static fn () => (new ClimateApi())->fetch($lat, $lon, $start, $end)
            );
        } catch (RuntimeException $e) {
            return [null, $e->getMessage()];
        }

        return [$data, null];
    }

    private function filename(array $in, string $extension): string
    {
        return sprintf(
            'climate_%s_%s_%s_%s.%s',
            (float) $in['latitude'],
            (float) $in['longitude'],
            $in['start_date'],
            $in['end_date'],
            $extension
        );
    }

    /** Turns the column-oriented "daily" block into one object per day. */
    private function toRecords(array $data): array
    {
        $columns = array_keys($data['daily_units']);
        $daily   = $data['daily'];
        $records = [];

        foreach ($daily['time'] as $i => $time) {
            $record = [];
            foreach ($columns as $column) {
                $record[$column] = $daily[$column][$i] ?? null;
            }
            $records[] = $record;
        }

        return $records;
    }
}