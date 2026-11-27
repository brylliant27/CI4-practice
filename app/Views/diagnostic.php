<?php
/**
 * @var array<string, mixed> $commune
 * @var array<string, mixed> $culture
 * @var array<string, mixed> $modele
 * @var list<array<string, mixed>> $horizons
 * @var array<string, array<string, mixed>> $indicateurs
 */
$gel       = $indicateurs['jours_gel'];
$autres    = array_diff_key($indicateurs, ['jours_gel' => true]);
$reference = $horizons[0];
$futur     = $horizons[count($horizons) - 1];

/** Source affichée telle qu'écrite par le pôle DATA ; « a_completer » devient « non sourcé ». */
$source = trim(str_replace('a_completer', 'non sourcé', (string) ($culture['source'] ?? '')));
$source = $source === '' ? 'non sourcé' : $source;
?>
<?= $this->extend('layout') ?>

<?= $this->section('titre') ?><?= esc($commune['nom']) ?> · <?= esc($culture['nom']) ?><?= $this->endSection() ?>

<?= $this->section('contenu') ?>

<p><a href="<?= site_url('/') ?>">← Nouveau diagnostic</a></p>

<section class="carte">
    <h1><?= esc($culture['nom']) ?> à <?= esc($commune['nom']) ?></h1>
    <p class="discret">
        Comparaison <span class="pastille ref"></span><?= esc($reference['libelle']) ?> (<?= $reference['annee_debut'] ?>–<?= $reference['annee_fin'] ?>)
        et <span class="pastille fut"></span><?= esc($futur['libelle']) ?> (<?= $futur['annee_debut'] ?>–<?= $futur['annee_fin'] ?>),
        modèle climatique <?= esc($modele['libelle']) ?>.
    </p>

    <h2>Jours de gel</h2>
    <?php if (! $gel['calculable']): ?>
        <p class="message alerte">Le seuil de gel de cette culture n'est pas renseigné dans le référentiel : l'indicateur ne peut pas être calculé.</p>
    <?php else: ?>
        <p><span class="etiquette">Seuil de gel de la culture : <?= fr_nombre($gel['valeur_seuil']) ?> °C</span></p>
        <p class="discret">Pour chaque année, on compte les jours où la température minimale passe
            strictement sous <?= fr_nombre($gel['valeur_seuil']) ?> °C. On obtient dix nombres par période,
            dont on fait la moyenne : c'est le nombre de jours de gel « par an » ci-dessous.</p>

        <?php $maxi = max(1, ...array_map(static fn ($p) => (float) $p['valeur'], $gel['periodes'])); ?>
        <div class="barres">
            <?php foreach ($gel['periodes'] as $i => $p): ?>
                <div class="barre-ligne">
                    <span class="barre-libelle"><?= esc($p['libelle']) ?> (<?= $p['annee_debut'] ?>–<?= $p['annee_fin'] ?>)</span>
                    <div class="barre-fond">
                        <div class="barre <?= $i === 0 ? 'ref' : 'fut' ?>" style="width: <?= round((float) $p['valeur'] / $maxi * 100, 1) ?>%"></div>
                    </div>
                    <strong class="barre-valeur"><?= fr_nombre($p['valeur']) ?> j/an</strong>
                </div>
            <?php endforeach ?>
        </div>

        <p class="verdict"><?= esc($gel['evolution']) ?></p>

        <h3>Année par année</h3>
        <p class="discret">Chaque barre est une année. Survolez-la pour lire sa valeur. D'une année à l'autre le
            nombre varie beaucoup : c'est pour cela qu'on compare des moyennes sur dix ans, jamais des années isolées.</p>
        <?php
        $maxiAnnuel = 1;
        foreach ($gel['periodes'] as $p) {
            foreach ($p['annees'] as $a) {
                $maxiAnnuel = max($maxiAnnuel, (float) $a['valeur']);
            }
        }
        ?>
        <div class="annuel">
            <?php foreach ($gel['periodes'] as $i => $p): ?>
                <figure class="annuel-serie">
                    <figcaption><span class="pastille <?= $i === 0 ? 'ref' : 'fut' ?>"></span><?= esc($p['libelle']) ?></figcaption>
                    <div class="annuel-trace">
                        <?php foreach ($p['annees'] as $annee => $a): ?>
                            <div class="annuel-colonne" title="<?= $annee ?> : <?= $a['valeur'] === null ? 'pas de donnée' : fr_nombre($a['valeur']) . ' jour(s) de gel' ?>">
                                <div class="annuel-barre <?= $i === 0 ? 'ref' : 'fut' ?>" style="height: <?= round((float) $a['valeur'] / $maxiAnnuel * 100, 1) ?>%"></div>
                                <span class="annuel-annee"><?= substr((string) $annee, 2) ?></span>
                            </div>
                        <?php endforeach ?>
                    </div>
                </figure>
            <?php endforeach ?>
        </div>
        <p class="discret">Échelle commune aux deux périodes : de 0 à <?= fr_nombre($maxiAnnuel) ?> jours de gel.</p>
    <?php endif ?>
</section>

<section class="carte">
    <h2>Autres indicateurs</h2>
    <div class="tableau-defilant">
        <table>
            <thead>
                <tr>
                    <th scope="col">Indicateur</th>
                    <th scope="col">Règle du jour</th>
                    <th scope="col"><?= $reference['annee_debut'] ?>–<?= $reference['annee_fin'] ?></th>
                    <th scope="col"><?= $futur['annee_debut'] ?>–<?= $futur['annee_fin'] ?></th>
                    <th scope="col">Évolution</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($autres as $ind): ?>
                <tr>
                    <th scope="row"><?= esc($ind['libelle']) ?><br><span class="discret"><?= esc($ind['unite']) ?></span></th>
                    <td>
                        <?= esc($ind['regle']) ?>
                        <?php if ($ind['valeur_seuil'] !== null): ?><br><span class="discret">seuil : <?= fr_nombre($ind['valeur_seuil']) ?> °C</span><?php endif ?>
                    </td>
                    <?php if (! $ind['calculable']): ?>
                        <td colspan="3" class="discret">Seuil non renseigné dans le référentiel de la culture : non calculé.</td>
                    <?php else: ?>
                        <?php foreach ($ind['periodes'] as $p): ?>
                            <td class="nombre"><?= fr_nombre($p['valeur']) ?></td>
                        <?php endforeach ?>
                        <td><?= esc($ind['evolution']) ?></td>
                    <?php endif ?>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <p class="discret">Le cumul de chaleur est calculé sur l'année civile, pas sur le cycle de la culture :
        il sert à comparer les deux périodes, pas à prédire la date de maturité.</p>
    <p class="discret">Les indicateurs sont calculés par le module du pôle DATA (<code>calcul/indicateurs.py</code>)
        à partir des séries Open-Meteo ; seule leur moyenne par période est conservée.</p>

    <?php
    $manquants = 0;
    foreach ($indicateurs as $ind) {
        foreach ($ind['periodes'] as $p) {
            $manquants = max($manquants, $p['manquants']);
        }
    }
    ?>
    <?php if ($manquants > 0): ?>
        <p class="message alerte">Attention : jusqu'à <?= $manquants ?> journée(s) sans donnée sur une période.
            Elles sont ignorées dans les calculs (jamais remplacées par 0).</p>
    <?php endif ?>
</section>

<section class="carte avertissement">
    <h2>⚠️ Fiez-vous à l'évolution, pas aux nombres</h2>
    <p>On peut vérifier les modèles sur le passé, car on y connaît les températures réellement mesurées.
       Sur ces années-là, ils annoncent <strong>moins de jours de gel qu'il n'y en a eu en réalité</strong>.
       Les données sont réglées pour que les températures moyennes soient justes, mais ce réglage adoucit
       les nuits les plus froides.</p>
    <p>Le nombre de la période de référence est donc trop bas, et celui de l'horizon 2050 l'est très probablement
       aussi. En revanche, l'<strong>évolution</strong> entre les deux périodes reste la donnée la plus fiable.</p>
</section>

<section class="carte">
    <h2>D'où viennent ces données ?</h2>
    <dl class="sources">
        <dt>Projections climatiques</dt>
        <dd>Open-Meteo Climate API, modèle <?= esc($modele['libelle']) ?>
            (<?= esc($modele['institut']) ?>, maille d'environ <?= esc((string) $modele['resolution_km']) ?> km).
            <strong>Un seul modèle</strong> est interrogé : l'incertitude entre modèles n'est pas encore mesurée.</dd>
        <dt>Scénario d'émissions</dt>
        <dd>Open-Meteo ne propose qu'un seul scénario, proche du plus pessimiste.</dd>
        <dt>Point de calcul</dt>
        <dd>Centre de la commune : <?= fr_nombre($commune['latitude'], 2) ?>° N, <?= fr_nombre($commune['longitude'], 2) ?>° E.</dd>
        <dt>Seuils de « <?= esc($culture['nom']) ?> »</dt>
        <dd>Référentiel des cultures de l'équipe (pôle DATA). Source indiquée : <?= esc($source) ?>.</dd>
    </dl>
</section>

<section class="carte discret">
    <div class="actions">
        <a class="bouton secondaire" href="<?= site_url('mes-donnees/telecharger') ?>">Télécharger mes données (CSV)</a>
        <form method="post" action="<?= site_url('mes-donnees/effacer') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="bouton secondaire">Effacer mes données</button>
        </form>
    </div>
</section>

<?= $this->endSection() ?>
