<?php

use App\Libraries\ModuleCalcul;
use App\Libraries\SeriesClimat;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Le pont PHP → module de calcul Python du pôle DATA, avec le VRAI Python.
 * Même jeu d'essais que calcul/test_module_calcul.py : les deux côtés doivent être d'accord.
 *
 * @internal
 */
final class ModuleCalculTest extends CIUnitTestCase
{
    private const CULTURE = [
        'nom'                      => 'Culture de test',
        'seuil_gel_c'              => 0.0,
        'seuil_stress_thermique_c' => 35.0,
        't_base_c'                 => 6.0,
    ];

    private function periodes(): array
    {
        $series = SeriesClimat::lireCsv(SUPPORTPATH . 'Data/jeu-essais-series.csv');

        return [
            ['id_temps' => 1, 'annee_debut' => 2011, 'annee_fin' => 2020, 'series' => $series],
            ['id_temps' => 2, 'annee_debut' => 2041, 'annee_fin' => 2050, 'series' => $series],
        ];
    }

    public function testLePhpRecoitLesIndicateursDuPython(): void
    {
        $res = (new ModuleCalcul())->calculer(self::CULTURE, $this->periodes());

        $this->assertSame(['jours_gel', 'jours_stress', 'gdd', 'cumul_pluie'], array_keys($res));
        $this->assertSame('jours/an', $res['jours_gel']['unite']);
        $this->assertEquals(1.0, $res['jours_gel']['periodes']['1']['moyenne']);
        $this->assertSame(['valeur' => 2.0, 'manquants' => 1], $res['jours_gel']['periodes']['1']['annees']['2015']);
        $this->assertNull($res['jours_gel']['periodes']['1']['annees']['2011']['valeur']);
        $this->assertEquals(0.5, $res['jours_stress']['periodes']['2']['moyenne']);
        $this->assertEquals(7.6, $res['cumul_pluie']['periodes']['1']['moyenne']);
    }

    public function testSeuilAbsentSignaleParLePython(): void
    {
        $res = (new ModuleCalcul())->calculer(['seuil_gel_c' => null] + self::CULTURE, $this->periodes());

        $this->assertFalse($res['jours_gel']['calculable']);
        $this->assertTrue($res['cumul_pluie']['calculable']);
    }

    public function testScriptIntrouvable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('introuvable');
        (new ModuleCalcul(null, WRITEPATH . 'nexiste-pas.py'))->calculer(self::CULTURE, []);
    }

    /** Un print() oublié dans indicateurs.py casserait le JSON : erreur claire, pas de page blanche. */
    public function testSortieQuiNEstPasDuJson(): void
    {
        $script = WRITEPATH . 'module-bavard.py';
        file_put_contents($script, "import sys\nprint('debug')\nprint('trace', file=sys.stderr)\nsys.exit(0)\n");

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('réponse illisible');
            (new ModuleCalcul(null, $script))->calculer(self::CULTURE, []);
        } finally {
            unlink($script);
        }
    }

    public function testErreurRenvoyeeParLeModule(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Module de calcul : KeyError');
        // une période sans « series » : le module répond {"statut": "erreur", ...}
        (new ModuleCalcul())->calculer(self::CULTURE, [['id_temps' => 1, 'annee_debut' => 2011, 'annee_fin' => 2020]]);
    }
}
