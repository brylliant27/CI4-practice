<?= $this->extend('layout') ?>

<?= $this->section('titre') ?>À propos<?= $this->endSection() ?>

<?= $this->section('contenu') ?>
<section class="carte">
    <h1>À propos</h1>
    <p>Climat &amp; Cultures aide un agriculteur, une collectivité ou une coopérative à juger si une culture
       restera adaptée au climat de sa commune. Elle compare deux périodes de dix ans simulées par un modèle
       climatique (2011-2020 et 2041-2050) et en tire des indicateurs agro-climatiques.</p>

    <h2>Comment c'est calculé</h2>
    <ol>
        <li>Le serveur demande à l'API Open-Meteo les séries climatiques journalières de la commune
            (températures minimale, maximale et moyenne, précipitations).</li>
        <li>Chaque journée est comparée aux seuils de la culture (règle du jour), puis on compte ou on
            additionne sur l'année.</li>
        <li>On fait la moyenne des dix années de chaque période, puis on compare les deux périodes.</li>
    </ol>

    <h2>Limites à connaître</h2>
    <ul>
        <li><strong>Un seul modèle climatique</strong> (MRI-AGCM3-2-S) : l'écart entre modèles, donc
            l'incertitude, n'est pas encore mesuré. Aucun indice de confiance n'est affiché.</li>
        <li><strong>Un seul scénario d'émissions</strong>, proche du plus pessimiste : c'est le seul que
            propose Open-Meteo.</li>
        <li>Les valeurs absolues des modèles sont biaisées (ils sous-estiment les nuits les plus froides) :
            l'évolution entre les deux périodes est plus fiable que les nombres eux-mêmes.</li>
        <li>Les seuils des cultures viennent du référentiel de l'équipe ; lorsqu'aucune source n'a été
            trouvée, la page l'indique (« non sourcé »).</li>
    </ul>

    <h2>Le projet</h2>
    <p>Projet pédagogique de la SAÉ 3.01 (BUT Informatique), réalisé par l'équipe 04 du groupe FI2.
       Application PHP / CodeIgniter 4, base MariaDB, hébergée sur Alwaysdata.</p>
</section>
<?= $this->endSection() ?>
