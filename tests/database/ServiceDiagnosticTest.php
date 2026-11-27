<?php

use App\Database\Seeds\ReferentielSeeder;
use App\Libraries\ServiceDiagnostic;
use App\Models\FactIndicateurModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\FauxClimateApi;

/**
 * Le diagnostic de bout en bout, sur une vraie base (SQLite en mémoire), avec la
 * vraie migration et le vrai seeder, mais une fausse API.
 *
 * @internal
 */
final class ServiceDiagnosticTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = ReferentielSeeder::class;

    private const SESSION_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const SESSION_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const TOULOUSE  = 13;
    private const MAIS      = 4; // seuil de gel 0 °C

    private FauxClimateApi $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->api = new FauxClimateApi();
    }

    public function testCalculeEcritPuisRelitLesFaits(): void
    {
        $resultat = (new ServiceDiagnostic($this->api))->executer(self::SESSION_A, self::TOULOUSE, self::MAIS);

        $gel = $resultat['indicateurs']['jours_gel'];
        $this->assertTrue($gel['calculable']);
        $this->assertSame([3.0, 1.0], array_column($gel['periodes'], 'valeur'));
        $this->assertSame("Environ 2 jours de gel de moins par an d'ici 2050 (3 → 1 jours/an selon le modèle).", $gel['evolution']);
        $this->assertCount(10, $gel['periodes'][0]['annees']);

        // 4 indicateurs × 2 horizons, rattachés à la session A ; un appel API par horizon
        $this->seeNumRecords(8, 'fact_indicateur', ['id_session' => self::SESSION_A]);
        $this->seeInDatabase('fact_indicateur', ['id_session' => self::SESSION_A, 'code_indicateur' => 'jours_gel', 'id_temps' => 1, 'valeur' => 3.0]);
        $this->assertSame(2, $this->api->appels);
    }

    public function testRelancerLeMemeDiagnosticNeCreePasDeDoublon(): void
    {
        $service = new ServiceDiagnostic($this->api);
        $service->executer(self::SESSION_A, self::TOULOUSE, self::MAIS);
        $service->executer(self::SESSION_A, self::TOULOUSE, self::MAIS);

        $this->seeNumRecords(8, 'fact_indicateur', ['id_session' => self::SESSION_A]);
    }

    /** RGPD : effacer les données d'un visiteur ne touche pas à celles d'un autre. */
    public function testEffacerUneSessionNeTouchePasAuxAutres(): void
    {
        $service = new ServiceDiagnostic($this->api);
        $service->executer(self::SESSION_A, self::TOULOUSE, self::MAIS);
        $service->executer(self::SESSION_B, self::TOULOUSE, self::MAIS);

        $effaces = model(FactIndicateurModel::class)->effacerSession(self::SESSION_A);

        $this->assertSame(8, $effaces);
        $this->seeNumRecords(0, 'fact_indicateur', ['id_session' => self::SESSION_A]);
        $this->seeNumRecords(8, 'fact_indicateur', ['id_session' => self::SESSION_B]);
    }

    public function testUnVisiteurNeRelitQueSesPropresFaits(): void
    {
        $service = new ServiceDiagnostic($this->api);
        $service->executer(self::SESSION_A, self::TOULOUSE, self::MAIS);

        $this->assertSame([], model(FactIndicateurModel::class)->lire(self::SESSION_B, self::TOULOUSE, self::MAIS, 1));
        $this->assertSame([], model(FactIndicateurModel::class)->exporterSession(self::SESSION_B));
        $this->assertCount(8, model(FactIndicateurModel::class)->exporterSession(self::SESSION_A));
    }

    public function testSeuilAbsentNInventePasDeFait(): void
    {
        $this->db->table('dim_culture')->where('id_culture', self::MAIS)->update(['seuil_stress_thermique_c' => null]);

        $resultat = (new ServiceDiagnostic($this->api))->executer(self::SESSION_A, self::TOULOUSE, self::MAIS);

        $this->assertFalse($resultat['indicateurs']['jours_stress']['calculable']);
        $this->seeNumRecords(6, 'fact_indicateur', ['id_session' => self::SESSION_A]);
    }

    public function testCommuneInconnue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ServiceDiagnostic($this->api))->executer(self::SESSION_A, 999, self::MAIS);
    }

    public function testPurgeDesResultatsDePlusDe30Jours(): void
    {
        $service = new ServiceDiagnostic($this->api);
        $service->executer(self::SESSION_A, self::TOULOUSE, self::MAIS);
        $service->executer(self::SESSION_B, self::TOULOUSE, self::MAIS);
        $this->db->table('fact_indicateur')->where('id_session', self::SESSION_A)
            ->update(['date_calcul' => date('Y-m-d H:i:s', strtotime('-31 days'))]);

        $purges = model(FactIndicateurModel::class)->purgerAvant(date('Y-m-d H:i:s', strtotime('-30 days')));

        $this->assertSame(8, $purges);
        $this->seeNumRecords(8, 'fact_indicateur', ['id_session' => self::SESSION_B]);
    }
}
