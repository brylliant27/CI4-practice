<?php

use App\Libraries\Indicateurs;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Présentation des indicateurs (pôle DEV).
 *
 * Le CALCUL des indicateurs appartient au pôle DATA et se teste en Python :
 *   python calcul/test_module_calcul.py
 * Le pont PHP → Python est testé dans ModuleCalculTest.
 *
 * @internal
 */
final class IndicateursTest extends CIUnitTestCase
{
    public function testPhrasesDEvolution(): void
    {
        $this->assertSame(
            "Environ 9 jours de gel de moins par an d'ici 2050 (16,5 → 7,5 jours/an selon le modèle).",
            Indicateurs::evolution('jours_gel', 16.5, 7.5, 'jours/an')
        );
        $this->assertSame(
            "Environ 1 jour de gel de plus par an d'ici 2050 (2 → 3 jours/an selon le modèle).",
            Indicateurs::evolution('jours_gel', 2.0, 3.0, 'jours/an')
        );
        $this->assertStringStartsWith('Le nombre de jours de gel resterait globalement stable', Indicateurs::evolution('jours_gel', 2.0, 2.4, 'jours/an'));
        $this->assertSame('800,6 → 779,4 mm/an (-3 %) selon le modèle.', Indicateurs::evolution('cumul_pluie', 800.6, 779.4, 'mm/an'));
        $this->assertStringStartsWith('Évolution non calculable', Indicateurs::evolution('jours_gel', null, 1.0, 'jours/an'));
    }

    /** Un indicateur ajouté par DATA, pas encore décrit côté PHP, s'affiche quand même. */
    public function testIndicateurInconnuDuPhpResteAffichable(): void
    {
        $this->assertSame('additionner', Indicateurs::definition('secheresse_max')['agregation']);
        $this->assertSame('10 → 12 jours (+20 %) selon le modèle.', Indicateurs::evolution('secheresse_max', 10.0, 12.0, 'jours'));
        $this->assertSame(['jours_gel', 'gdd', 'secheresse_max'], Indicateurs::ordre(['secheresse_max', 'gdd', 'jours_gel']));
    }
}
