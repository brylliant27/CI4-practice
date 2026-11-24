<?php

use App\Database\Seeds\ReferentielSeeder;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\FauxClimateApi;

/**
 * Le parcours vu du navigateur : routes, codes HTTP, contenu des pages.
 *
 * @internal
 */
final class ParcoursUtilisateurTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = ReferentielSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Services::injectMock('climateApi', new FauxClimateApi());
    }

    public function testAccueilProposeCommunesEtCultures(): void
    {
        $page = $this->get('/');

        $page->assertOK();
        $page->assertSee('Toulouse');
        $page->assertSee('Maïs grain');
        $page->assertSee('Effacer mes données');
    }

    public function testDiagnosticComplet(): void
    {
        $page = $this->get('diagnostic', ['commune' => 13, 'culture' => 4]);

        $page->assertOK();
        $page->assertSee('Maïs grain à Toulouse');
        $page->assertSee("Environ 2 jours de gel de moins par an d'ici 2050");
        $page->assertSee('Un seul modèle');
        $page->assertDontSee('médian');
    }

    public function testParametresInvalides(): void
    {
        $this->get('diagnostic', ['commune' => 'abc', 'culture' => 4])->assertStatus(400);
        $this->get('diagnostic', ['commune' => 999, 'culture' => 4])->assertStatus(404);
    }

    public function testPagesDInformation(): void
    {
        $this->get('a-propos')->assertOK();
        $this->get('donnees-personnelles')->assertSee('30 jours');
    }

    public function testExportCsvAuFormatDuContrat(): void
    {
        $reponse = $this->get('series/csv', ['commune' => 13, 'debut' => '2015-01-01', 'fin' => '2015-01-03']);

        $reponse->assertStatus(200);
        $this->assertInstanceOf(DownloadResponse::class, $reponse->response());

        // Un téléchargement n'expose pas son contenu : on capture ce qu'il enverrait.
        ob_start();
        $reponse->response()->sendBody();
        $lignes = explode("\n", trim(ob_get_clean()));
        $this->assertSame('id_commune,date,t_min,t_max,t_moy,precip,et0', $lignes[0]);
        $this->assertSame('13,2015-01-01,-1,20,10,1,2', $lignes[1]);
        $this->assertCount(4, $lignes);
    }

    public function testExportRefuseUnePeriodeTropLongue(): void
    {
        $this->get('series/csv', ['commune' => 13, 'debut' => '2000-01-01', 'fin' => '2015-12-31'])->assertStatus(400);
    }
}
