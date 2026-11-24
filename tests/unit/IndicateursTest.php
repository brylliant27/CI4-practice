<?php

use App\Libraries\Indicateurs;
use App\Libraries\SeriesClimat;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Jeu d'essais des indicateurs : des séries dont on connaît le résultat à l'avance.
 * Fichier : tests/_support/Data/jeu-essais-series.csv (format du contrat d'interface).
 * Les résultats attendus sont calculés à la main dans docs/contrat-interface.md.
 *
 * Culture de test : seuil de gel 0 °C, stress thermique 35 °C, température de base 6 °C.
 *
 * @internal
 */
final class IndicateursTest extends CIUnitTestCase
{
    private const CULTURE = [
        'seuil_gel_c'              => 0.0,
        'seuil_stress_thermique_c' => 35.0,
        't_base_c'                 => 6.0,
    ];

    private array $series;

    protected function setUp(): void
    {
        parent::setUp();
        $this->series = SeriesClimat::lireCsv(SUPPORTPATH . 'Data/jeu-essais-series.csv');
    }

    /** Cas nominal + journée AU seuil + valeur manquante, sur l'année 2015. */
    public function testJoursDeGelParAnnee(): void
    {
        $annees = Indicateurs::parAnnee('jours_gel', $this->series, self::CULTURE, 2011, 2020);

        // 2015 : -5 ✓, -1 ✓, 0,0 ✓ (au seuil, décision ≤), 0,5 ✗, 1 jour sans t_min ignoré
        $this->assertSame(3.0, $annees[2015]['valeur']);
        $this->assertSame(1, $annees[2015]['manquants']);
        // 2016 : année SANS gel → 0, ce qui n'est pas une donnée manquante
        $this->assertSame(0.0, $annees[2016]['valeur']);
        // 2011 : aucune donnée → pas de valeur (et surtout pas 0)
        $this->assertNull($annees[2011]['valeur']);
    }

    public function testJoursDeGelMoyenneSurLHorizon(): void
    {
        $reference = Indicateurs::parAnnee('jours_gel', $this->series, self::CULTURE, 2011, 2020);
        $futur     = Indicateurs::parAnnee('jours_gel', $this->series, self::CULTURE, 2041, 2050);

        // (3 + 0) / 2 : l'année sans gel compte, les années sans donnée sont exclues
        $this->assertSame(1.5, Indicateurs::moyenne($reference));
        // 2045 : 1 jour ; 2048 : le 29 février à -0,1 °C (année bissextile) → 1 jour
        $this->assertSame(1.0, Indicateurs::moyenne($futur));
    }

    /** Décision J2 : une journée exactement au seuil compte (≤ pour le gel, ≥ pour la chaleur). */
    public function testJourneeExactementAuSeuilCompte(): void
    {
        $this->assertSame(1, Indicateurs::valeurDuJour('jours_gel', ['t_min' => 0.0], self::CULTURE));
        $this->assertSame(0, Indicateurs::valeurDuJour('jours_gel', ['t_min' => 0.1], self::CULTURE));
        $this->assertSame(1, Indicateurs::valeurDuJour('stress_thermique', ['t_max' => 35.0], self::CULTURE));
        $this->assertSame(0, Indicateurs::valeurDuJour('stress_thermique', ['t_max' => 34.9], self::CULTURE));
    }

    /** En PHP, null <= 0 est vrai : un jour sans mesure ne doit PAS devenir un jour de gel. */
    public function testValeurManquanteNEstPasUnJourDeGel(): void
    {
        $this->assertNull(Indicateurs::valeurDuJour('jours_gel', ['t_min' => null], self::CULTURE));
        $this->assertNull(Indicateurs::valeurDuJour('gdd', ['t_moy' => null], self::CULTURE));
    }

    public function testStressThermique(): void
    {
        $reference = Indicateurs::parAnnee('stress_thermique', $this->series, self::CULTURE, 2011, 2020);
        $futur     = Indicateurs::parAnnee('stress_thermique', $this->series, self::CULTURE, 2041, 2050);

        $this->assertSame(2.0, $reference[2015]['valeur']); // 38 ✓, 35 ✓ (au seuil), 34,9 ✗
        $this->assertSame(1.0, Indicateurs::moyenne($reference));
        $this->assertSame(0.5, Indicateurs::moyenne($futur));
    }

    public function testCumulDeChaleur(): void
    {
        $reference = Indicateurs::parAnnee('gdd', $this->series, self::CULTURE, 2011, 2020);
        $futur     = Indicateurs::parAnnee('gdd', $this->series, self::CULTURE, 2041, 2050);

        $this->assertSame(60.5, $reference[2015]['valeur']); // 22 + 19,5 + 19 ; jours froids → 0
        $this->assertSame(1, $reference[2015]['manquants']);
        $this->assertSame(38.5, Indicateurs::moyenne($reference));
        $this->assertSame(12.0, Indicateurs::moyenne($futur));
    }

    public function testCumulDePluie(): void
    {
        $reference = Indicateurs::parAnnee('pluie', $this->series, self::CULTURE, 2011, 2020);
        $futur     = Indicateurs::parAnnee('pluie', $this->series, self::CULTURE, 2041, 2050);

        $this->assertSame(11.2, $reference[2015]['valeur']);
        $this->assertSame(7.6, Indicateurs::moyenne($reference));
        $this->assertSame(1.5, Indicateurs::moyenne($futur));
    }

    public function testSeuilNonRenseigneRendLIndicateurIncalculable(): void
    {
        $culture = ['seuil_gel_c' => null, 'seuil_stress_thermique_c' => 35.0, 't_base_c' => 6.0];

        $this->assertFalse(Indicateurs::calculable('jours_gel', $culture));
        $this->assertTrue(Indicateurs::calculable('pluie', $culture));

        $this->expectException(InvalidArgumentException::class);
        Indicateurs::parAnnee('jours_gel', $this->series, $culture, 2011, 2020);
    }

    public function testMoyenneSansAucuneDonnee(): void
    {
        $this->assertNull(Indicateurs::moyenne([2041 => ['valeur' => null], 2042 => ['valeur' => null]]));
    }

    public function testPhrasesDEvolution(): void
    {
        $this->assertSame(
            "Environ 9 jours de gel de moins par an d'ici 2050 (16,5 → 7,5 jours/an selon le modèle).",
            Indicateurs::evolution('jours_gel', 16.5, 7.5)
        );
        $this->assertSame(
            "Environ 1 jour de gel de plus par an d'ici 2050 (2 → 3 jours/an selon le modèle).",
            Indicateurs::evolution('jours_gel', 2.0, 3.0)
        );
        $this->assertStringStartsWith('Le nombre de jours de gel resterait globalement stable', Indicateurs::evolution('jours_gel', 2.0, 2.4));
        $this->assertSame('800,6 → 779,4 mm/an (-3 %) selon le modèle.', Indicateurs::evolution('pluie', 800.6, 779.4));
        $this->assertStringStartsWith('Évolution non calculable', Indicateurs::evolution('jours_gel', null, 1.0));
    }
}
