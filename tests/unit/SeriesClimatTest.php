<?php

use App\Libraries\SeriesClimat;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Le contrat d'interface : ce que le pôle DEV livre au pôle DATA.
 *
 * @internal
 */
final class SeriesClimatTest extends CIUnitTestCase
{
    private const REPONSE_API = [
        'daily' => [
            'time'                           => ['2015-01-01', '2015-01-02'],
            'temperature_2m_min'             => [-5.2, null],
            'temperature_2m_max'             => [2.0, 6.1],
            'temperature_2m_mean'            => [-1.5, 2.5],
            'precipitation_sum'              => [0.0, 1.2],
            'et0_fao_evapotranspiration_sum' => [0.31, 0.4],
        ],
    ];

    public function testTransformeLaReponseApiAuFormatDuContrat(): void
    {
        $series = SeriesClimat::depuisApi(13, self::REPONSE_API);

        $this->assertSame([
            ['id_commune' => 13, 'date' => '2015-01-01', 't_min' => -5.2, 't_max' => 2.0, 't_moy' => -1.5, 'precip' => 0.0, 'et0' => 0.31],
            ['id_commune' => 13, 'date' => '2015-01-02', 't_min' => null, 't_max' => 6.1, 't_moy' => 2.5, 'precip' => 1.2, 'et0' => 0.4],
        ], $series);
    }

    public function testVariableAbsenteDonneUneColonneVide(): void
    {
        $reponse = self::REPONSE_API;
        unset($reponse['daily']['et0_fao_evapotranspiration_sum']);

        $series = SeriesClimat::depuisApi(13, $reponse);

        $this->assertNull($series[0]['et0']);
        $this->assertNull($series[1]['et0']);
    }

    public function testRefuseUneReponseIncoherente(): void
    {
        $reponse                                = self::REPONSE_API;
        $reponse['daily']['temperature_2m_min'] = [-5.2];

        $this->expectException(UnexpectedValueException::class);
        SeriesClimat::depuisApi(13, $reponse);
    }

    /** Le défaut de l'ancien échantillon : des dates réécrites par Excel (10/1/2026). */
    public function testRefuseUneDateNonIso(): void
    {
        $reponse                  = self::REPONSE_API;
        $reponse['daily']['time'] = ['1/1/2015', '1/2/2015'];

        $this->expectException(UnexpectedValueException::class);
        SeriesClimat::depuisApi(13, $reponse);
    }

    public function testCsvConformeEtRelisible(): void
    {
        $series = SeriesClimat::depuisApi(13, self::REPONSE_API);
        $csv    = SeriesClimat::versCsv($series);

        $lignes = explode("\n", trim($csv));
        $this->assertSame('id_commune,date,t_min,t_max,t_moy,precip,et0', $lignes[0]);
        $this->assertSame('13,2015-01-01,-5.2,2,-1.5,0,0.31', $lignes[1]);
        $this->assertSame('13,2015-01-02,,6.1,2.5,1.2,0.4', $lignes[2]); // valeur manquante = cellule vide

        $fichier = tempnam(sys_get_temp_dir(), 'series');
        file_put_contents($fichier, $csv);
        $relu = SeriesClimat::lireCsv($fichier);
        unlink($fichier);

        $this->assertSame($series, $relu);
    }

    public function testLeJeuDEssaisRespecteLeContrat(): void
    {
        $series = SeriesClimat::lireCsv(SUPPORTPATH . 'Data/jeu-essais-series.csv');

        $this->assertCount(14, $series);
        $this->assertNull($series[4]['t_min']); // 2015-01-05 : valeur manquante
    }

    public function testRefuseUnEnTeteNonConforme(): void
    {
        $fichier = tempnam(sys_get_temp_dir(), 'series');
        file_put_contents($fichier, "time,temperature_2m_min\n2015-01-01,-5\n");

        try {
            $this->expectException(UnexpectedValueException::class);
            SeriesClimat::lireCsv($fichier);
        } finally {
            unlink($fichier);
        }
    }
}
