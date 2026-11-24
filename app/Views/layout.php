<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php // Chaque vue échappe elle-même le contenu de sa section « titre ». ?>
    <title><?= $this->renderSection('titre') ?: 'Accueil' ?> · Climat &amp; Cultures</title>
    <link rel="stylesheet" href="<?= base_url('css/style.css') ?>">
</head>
<body>
    <header class="entete">
        <div class="conteneur entete-interieur">
            <a class="marque" href="<?= site_url('/') ?>">🌱 Climat &amp; Cultures</a>
            <nav aria-label="Navigation principale">
                <a href="<?= site_url('/') ?>">Diagnostic</a>
                <a href="<?= site_url('a-propos') ?>">À propos</a>
                <a href="<?= site_url('donnees-personnelles') ?>">Données personnelles</a>
            </nav>
        </div>
    </header>

    <main class="conteneur">
        <?= $this->renderSection('contenu') ?>
    </main>

    <footer class="pied">
        <div class="conteneur">
            <p>SAÉ 3.01 « Climat &amp; Cultures » · équipe 04 · projet pédagogique.</p>
            <p>Projections climatiques : <a href="https://open-meteo.com/en/docs/climate-api">Open-Meteo Climate API</a>
               (licence CC BY 4.0), modèle MRI-AGCM3-2-S.
               · <a href="<?= site_url('donnees-personnelles') ?>">Données personnelles</a>
               · <a href="<?= site_url('series') ?>">Export des séries (contrat d'interface)</a></p>
        </div>
    </footer>
</body>
</html>
