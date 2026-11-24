<?= $this->extend('layout') ?>

<?= $this->section('titre') ?>Diagnostic<?= $this->endSection() ?>

<?= $this->section('contenu') ?>

<?php if ($efface !== null): ?>
    <p class="message succes" role="status">
        Vos données ont été effacées (<?= $efface ?> ligne<?= $efface > 1 ? 's' : '' ?>) et votre identifiant a été oublié.
    </p>
<?php endif ?>

<section class="carte">
    <h1>Cette culture restera-t-elle adaptée au climat de ma commune ?</h1>
    <p class="intro">
        Choisissez une commune et une culture. L'application compare le climat simulé de
        <strong>2011-2020</strong> à celui de <strong>2041-2050</strong> et calcule des indicateurs
        agro-climatiques à partir des seuils de la culture : jours de gel, jours de stress thermique,
        cumul de chaleur et précipitations.
    </p>

    <form class="formulaire" method="get" action="<?= site_url('diagnostic') ?>">
        <label>
            Commune
            <select name="commune" required>
                <option value="">— choisir —</option>
                <?php foreach ($communes as $c): ?>
                    <option value="<?= $c['id_commune'] ?>"><?= esc($c['nom']) ?> (<?= esc($c['zone_climatique']) ?>)</option>
                <?php endforeach ?>
            </select>
        </label>
        <label>
            Culture
            <select name="culture" required>
                <option value="">— choisir —</option>
                <?php foreach ($cultures as $c): ?>
                    <option value="<?= $c['id_culture'] ?>"><?= esc($c['nom']) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <button type="submit" class="bouton">Lancer le diagnostic</button>
    </form>
    <p class="discret">La première consultation d'une commune interroge l'API climatique : comptez quelques secondes.</p>
</section>

<section class="carte discret">
    <h2>Vos données</h2>
    <p>L'application ne vous demande ni nom ni e-mail. Vos résultats sont rattachés à un identifiant
       aléatoire, effacé à la fermeture du navigateur ou à votre demande.
       <a href="<?= site_url('donnees-personnelles') ?>">En savoir plus</a>.</p>
    <div class="actions">
        <a class="bouton secondaire" href="<?= site_url('mes-donnees/telecharger') ?>">Télécharger mes données (CSV)</a>
        <form method="post" action="<?= site_url('mes-donnees/effacer') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="bouton secondaire">Effacer mes données</button>
        </form>
    </div>
</section>

<?= $this->endSection() ?>
