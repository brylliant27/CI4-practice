<?= $this->extend('layout') ?>

<?= $this->section('titre') ?>Erreur<?= $this->endSection() ?>

<?= $this->section('contenu') ?>
<section class="carte">
    <h1><?= $statut === 404 ? 'Introuvable' : ($statut === 400 ? 'Demande incomplète' : 'Service momentanément indisponible') ?></h1>
    <p class="message alerte"><?= esc($message) ?></p>
    <p><a class="bouton" href="<?= site_url('/') ?>">← Retour à l'accueil</a></p>
</section>
<?= $this->endSection() ?>
