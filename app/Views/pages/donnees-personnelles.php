<?= $this->extend('layout') ?>

<?= $this->section('titre') ?>Données personnelles<?= $this->endSection() ?>

<?= $this->section('contenu') ?>
<section class="carte">
    <h1>Données personnelles</h1>
    <p>Cette page vous informe, comme le demande le RGPD, de ce que l'application fait de vos données.</p>

    <h2>Ce que nous conservons</h2>
    <table>
        <thead><tr><th scope="col">Donnée</th><th scope="col">Pourquoi</th><th scope="col">Combien de temps</th></tr></thead>
        <tbody>
            <tr><td>Un identifiant aléatoire, rattaché à votre session</td>
                <td>Retrouver vos résultats et les isoler de ceux des autres visiteurs</td>
                <td>Jusqu'à la fermeture du navigateur</td></tr>
            <tr><td>La commune et la culture choisies, les résultats calculés, la date du calcul</td>
                <td>Afficher le diagnostic demandé</td>
                <td>30 jours au plus, puis suppression automatique</td></tr>
        </tbody>
    </table>
    <p><strong>Ce que nous ne collectons pas :</strong> ni nom, ni e-mail, ni géolocalisation (la commune est
       choisie dans une liste), ni adresse IP. L'appel au service climatique Open-Meteo ne transmet que les
       coordonnées du centre de la commune, aucune information sur vous.</p>

    <h2>Base légale et cookie</h2>
    <p>Finalité unique : fournir le diagnostic demandé. Aucune revente, aucun profilage, aucune publicité.
       Base légale : l'intérêt légitime à faire fonctionner le service (article 6.1.f du RGPD).
       Le cookie de session est strictement nécessaire au service : il est exempté de consentement
       (lignes directrices de la CNIL du 1er octobre 2020).</p>

    <h2>Vos droits</h2>
    <ul>
        <li><strong>Accès et portabilité :</strong> le bouton « Télécharger mes données » vous donne, en CSV,
            tout ce qui est rattaché à votre identifiant.</li>
        <li><strong>Effacement :</strong> le bouton « Effacer mes données » supprime vos résultats, et
            uniquement les vôtres, puis oublie votre identifiant.</li>
    </ul>
    <div class="actions">
        <a class="bouton secondaire" href="<?= site_url('mes-donnees/telecharger') ?>">Télécharger mes données (CSV)</a>
        <form method="post" action="<?= site_url('mes-donnees/effacer') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="bouton secondaire">Effacer mes données</button>
        </form>
    </div>

    <h2>Hébergement et responsable</h2>
    <p>Hébergeur : Alwaysdata (infrastructure en France), sous-traitant au sens du RGPD. Aucun transfert de
       données hors de l'Union européenne. Responsable : équipe 04, SAÉ 3.01, IUT de Créteil-Vitry
       (projet pédagogique).</p>
</section>
<?= $this->endSection() ?>
