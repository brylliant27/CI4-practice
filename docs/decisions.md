# Décisions techniques — équipe 04

*Mise à jour : 24 novembre 2026. Chaque décision est proposée par le pôle DEV et **doit être validée par l'équipe** (cocher la case une fois validée).*

## D1 — Une seule pile technique

- [ ] validée par l'équipe

**Décision** : PHP 8.2+ avec **CodeIgniter 4**, base **MariaDB/MySQL**, hébergement **Alwaysdata**.

**Pourquoi** : c'est la pile annoncée dans le document de conception, et la seule qui a du code qui tourne de bout en bout. CodeIgniter tient dans les 100 Mo d'Alwaysdata, qui fournit MariaDB, et WampServer en local fournit aussi MariaDB (port 3307).

**Ce qui est abandonné** : le prototype Flask/Python du dépôt `SA-3.01-Climat-Cultures` (le kit du prof a servi de modèle et a été porté en PHP) et SQLite à l'exécution. SQLite ne sert plus qu'aux **tests automatiques**, dans une base en mémoire. Le schéma est créé par la migration CodeIgniter (`php spark migrate`), qui génère le SQL adapté à la base utilisée.

## D2 — Journée exactement au seuil : `≤` pour le gel, `≥` pour la chaleur

- [ ] validée par l'équipe

**Décision** : un jour est gelé si `t_min ≤ seuil_gel_c`. Un jour est en stress thermique si `t_max ≥ seuil_stress_thermique_c`.

**Pourquoi** : par prudence. Une journée qui atteint le seuil atteint la température qui endommage la plante. Pour un agriculteur qui engage un investissement de 20 ou 30 ans, mieux vaut signaler un risque de trop qu'en manquer un. Le kit utilise `<` : notre choix compte un peu plus de jours, et l'écart reste faible (sur Toulouse 2015, aucun jour n'est exactement à 0 °C).

**Où** : `app/Libraries/Indicateurs.php`, méthode `valeurDuJour`. Un test (`testJourneeExactementAuSeuilCompte`) échoue si on change la règle.

## D3 — Valeur manquante : ignorée et signalée, jamais remplacée par 0

- [ ] validée par l'équipe

**Décision** : une journée sans donnée pour la variable utilisée est **ignorée** et comptée dans « manquants ». Une année sans **aucune** donnée n'a pas de valeur et est exclue de la moyenne de l'horizon. Une année **sans gel** vaut 0 et compte dans la moyenne. Si une période contient des journées manquantes, la page de résultat l'indique.

**Pourquoi** : en PHP, `null <= 0` est vrai. Remplacer un manque par 0 transformerait chaque jour sans mesure en jour de gel.

## D4 — Deux horizons de 10 ans

- [ ] validée par l'équipe

**Décision** : référence **2011-2020**, projection **2041-2050** (table `dim_temps`).

**Pourquoi** : c'est le choix du kit. Dix ans sont le minimum pour qu'une moyenne climatique ait un sens, et vingt ans doubleraient la consommation du quota Open-Meteo (environ 520 appels pondérés par commune au lieu de 1 040). Les réponses de l'API sont gardées en cache 30 jours.

## D5 — Un seul modèle : aucun indice de confiance affiché

- [ ] validée par l'équipe

**Décision** : le sprint 1 n'interroge que `MRI_AGCM3_2_S`. Les écrans **n'affichent aucun indice de confiance**. L'« indice de confiance du modèle : 85 % » des maquettes est retiré, car rien ne le calcule. Ils n'affichent pas non plus de « scénario médian » (Open-Meteo ne propose qu'un scénario, proche du plus pessimiste), ni de source que nous n'interrogeons pas (DRIAS, Météo-France, Copernicus).

**Règle pour tout écran** : *cette donnée, d'où vient-elle ?* Si on ne peut pas répondre, elle ne s'affiche pas. La page de résultat comporte un encadré « D'où viennent ces données ? ». Un seuil sans source y est marqué « non sourcé ».

**Suite (sprint 2)** : ajouter un second modèle, puis afficher une fourchette entre les deux.

## D6 — ET0 livrée mais pas affichée

- [ ] validée par l'équipe

**Décision** : la colonne `et0` est fournie dans les séries (contrat v1.0), mais aucun écran ne l'affiche tant que le pôle DATA ne l'a pas comparée à un calcul de Hargreaves.

**Pourquoi** : le retour du prof signale que ces valeurs paraissent nettement trop hautes. L'échantillon donne 2,01 mm le 1er janvier 2015 à Toulouse, ce qui semble élevé pour un jour d'hiver.

## D7 — Seuils des cultures : référentiel du kit en attendant celui du pôle DATA

- [ ] remplacé par le référentiel de l'équipe

**État actuel** : `app/Database/Seeds/data/referentiel-cultures.csv` est le référentiel du kit du prof, dont beaucoup de valeurs sont marquées `a_completer`. L'application affiche alors « non sourcé ».

**À faire (pôle DATA)** : remplacer ce fichier par le référentiel sourcé de l'équipe, en gardant les mêmes colonnes, puis lancer `php spark db:seed ReferentielSeeder`. Aucun code à modifier. La règle de l'amorçage s'applique : en cas d'écart faible entre sources, retenir la valeur la plus prudente et noter la fourchette ; sans aucune source, garder la valeur et écrire « non sourcé ».
