<?php
/**
 * @var list<array<string, mixed>>       $communes
 * @var array<string, string|null>       $entrees
 * @var string|null                      $erreur
 * @var array<string, mixed>|null        $commune
 * @var list<array<string, mixed>>|null  $series
 * @var string|null                      $requete
 */
use App\Libraries\SeriesClimat;

$colonnes = array_keys(SeriesClimat::COLONNES);
?>
<?= $this->extend('layout') ?>

<?= $this->section('titre') ?>Export des séries<?= $this->endSection() ?>

<?= $this->section('contenu') ?>
<section class="carte">
    <h1>Export des séries climatiques</h1>
    <p>Outil du pôle DEV pour le pôle DATA : il produit un échantillon de séries <strong>au format du contrat
       d'interface v<?= SeriesClimat::VERSION_CONTRAT ?></strong> (<?= SeriesClimat::DATE_CONTRAT ?>), directement
       depuis l'API Open-Meteo. Ne l'ouvrez pas et ne l'enregistrez pas avec Excel : il modifierait les dates.</p>

    <form class="formulaire" method="get" action="<?= site_url('series') ?>">
        <label>
            Commune
            <select name="commune" required>
                <?php foreach ($communes as $c): ?>
                    <option value="<?= $c['id_commune'] ?>" <?= (string) $c['id_commune'] === (string) ($entrees['commune'] ?? '13') ? 'selected' : '' ?>>
                        <?= esc($c['nom']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </label>
        <label>
            Début
            <input type="date" name="debut" min="1950-01-01" max="2050-12-31" required value="<?= esc($entrees['debut'] ?? '2015-01-01', 'attr') ?>">
        </label>
        <label>
            Fin
            <input type="date" name="fin" min="1950-01-01" max="2050-12-31" required value="<?= esc($entrees['fin'] ?? '2015-12-31', 'attr') ?>">
        </label>
        <button type="submit" class="bouton">Extraire</button>
    </form>
    <p class="discret">Période de 10 ans au plus. Les réponses de l'API sont gardées en cache 30 jours.</p>
</section>

<?php if ($erreur): ?>
    <p class="message alerte"><?= esc($erreur) ?></p>
<?php endif ?>

<?php if ($series): ?>
    <?php
    $manquants = 0;
    foreach ($series as $ligne) {
        foreach ($ligne as $v) {
            $manquants += $v === null ? 1 : 0;
        }
    }
    ?>
    <section class="carte">
        <h2><?= esc($commune['nom']) ?> : <?= count($series) ?> jours</h2>
        <p class="discret">Point : <?= fr_nombre($commune['latitude'], 2) ?>° N, <?= fr_nombre($commune['longitude'], 2) ?>° E
            · valeurs manquantes : <?= $manquants ?>.</p>
        <div class="actions">
            <a class="bouton" href="<?= site_url('series/csv') . '?' . esc($requete, 'attr') ?>">Télécharger le CSV</a>
            <a class="bouton secondaire" href="<?= site_url('series/json') . '?' . esc($requete, 'attr') ?>">Télécharger le JSON</a>
        </div>

        <h3>Aperçu (10 premières lignes)</h3>
        <div class="tableau-defilant">
            <table>
                <thead>
                    <tr><?php foreach ($colonnes as $col): ?><th scope="col"><?= esc($col) ?><br><span class="discret"><?= esc(SeriesClimat::UNITES[$col]) ?></span></th><?php endforeach ?></tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($series, 0, 10) as $ligne): ?>
                    <tr><?php foreach ($colonnes as $col): ?><td class="nombre"><?= $ligne[$col] === null ? '<em>vide</em>' : esc((string) $ligne[$col]) ?></td><?php endforeach ?></tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif ?>
<?= $this->endSection() ?>
