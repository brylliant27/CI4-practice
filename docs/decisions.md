# Décisions techniques — équipe 04

*Mise à jour : 27 novembre 2026. Chaque décision est proposée par un pôle et **doit être validée par l'équipe** (cocher la case une fois validée). Une décision remplacée reste dans le fichier, barrée dans son titre, avec la raison du changement.*

## D1 — Pile technique : application PHP, module de calcul Python du pôle DATA

- [ ] validée par l'équipe

**Décision** : l'application est en PHP 8.2+ avec **CodeIgniter 4**, base **MariaDB/MySQL**, hébergée sur **Alwaysdata**. Le **calcul des indicateurs** est un module **Python** du pôle DATA (`calcul/`), appelé par l'application à chaque diagnostic.

```
ClimateApi (DEV) ─► séries du contrat v1.0 ─► calcul/module_calcul.py (DATA) ─► valeurs ─► fact_indicateur (par session)
```

**Pourquoi** : l'énoncé fait du pôle DATA le propriétaire de la logique de calcul (« DEV produit les séries, DATA produit les faits »). Le pôle DATA ajoute un indicateur dans un seul fichier, `calcul/indicateurs.py`, sans toucher au PHP. Le module ne se connecte ni à la base ni à l'API, et n'utilise que la bibliothèque standard de Python : rien à installer sur Alwaysdata. L'application garde ce qui relève du pôle DEV : client API (cache, erreurs, quota), écriture des faits par session, affichage, RGPD.

**Contrat PHP ↔ Python** : `calcul/README.md`. Les codes d'indicateurs sont ceux du pôle DATA (`jours_gel`, `jours_stress`, `gdd`, `cumul_pluie`).

**Ce qui est abandonné** :
- le prototype Flask du dépôt `SA-3.01-Climat-Cultures` et SQLite à l'exécution (SQLite ne sert plus qu'aux tests automatiques, dans une base en mémoire) ;
- le calcul en PHP de la première version du MVP (`Indicateurs.php` ne sert plus qu'à l'affichage) ;
- la connexion directe du script `calculer.py` à la base et à l'API : il reste l'outil de test en console du pôle DATA, mais l'application ne l'utilise pas.

*Version précédente (24/11)* : « une seule pile, tout en PHP ». Remplacée le 27/11, quand l'équipe a confié le calcul au pôle DATA (PR #2).

## D2 — Journée exactement au seuil : règles du pôle DATA

- [ ] validée par l'équipe (pôle DATA)

**Décision** : un jour est gelé si `t_min < seuil_gel_c` (**strictement** sous le seuil). Un jour est en stress thermique si `t_max ≥ seuil_stress_thermique_c`. Le GDD prend la moyenne `(t_max + t_min) / 2`.

**Pourquoi** : ce sont les règles écrites par le pôle DATA, propriétaire du calcul : requêtes SQL du dossier J2 pour le gel et le GDD, `calcul/indicateurs.py` pour le stress thermique.

**Point ouvert pour le pôle DATA** : la règle de stress thermique est `>` dans le SQL du J2 mais `≥` dans le Python. C'est le Python qui s'applique, à confirmer.

**Où** : `calcul/indicateurs.py`. Le jeu d'essais `calcul/test_module_calcul.py` échoue si on change une règle : la journée du 3 janvier 2015, exactement à 0 °C, ne doit pas compter.

*Version précédente (24/11)* : `≤` pour le gel et `≥` pour la chaleur, par prudence (signaler un risque de trop plutôt qu'en manquer un). Remplacée le 27/11 : le calcul appartient au pôle DATA, et ce sont ses règles qui s'appliquent. L'écart reste faible : sur Toulouse 2015, aucun jour n'est exactement à 0 °C.

## D3 — Valeur manquante : ignorée et signalée, jamais remplacée par 0

- [ ] validée par l'équipe

**Décision** : une journée sans donnée pour une variable de l'indicateur est **ignorée** et comptée dans « manquants ». Une année sans **aucune** donnée exploitable n'a pas de valeur et est exclue de la moyenne de l'horizon. Une année **sans gel** vaut 0 et compte dans la moyenne. Si une période contient des journées manquantes, la page de résultat l'indique.

**Pourquoi** : remplacer un manque par 0 fausserait les moyennes. Une année entière sans donnée (panne de l'API, modèle incomplet) ferait baisser la moyenne comme si elle n'avait eu aucun jour de gel.

**Où** : les fonctions de `calcul/indicateurs.py` ignorent les `None` ; `calcul/module_calcul.py` exclut les années vides. Cette exclusion est une proposition du pôle DEV, à valider par le pôle DATA : leur `calculer.py` compte toutes les années.

## D4 — Deux horizons de 10 ans

- [ ] validée par l'équipe

**Décision** : référence **2011-2020**, projection **2041-2050** (table `dim_temps`).

**Pourquoi** : c'est le choix du kit. Dix ans sont le minimum pour qu'une moyenne climatique ait un sens, et vingt ans doubleraient la consommation du quota Open-Meteo (environ 520 appels pondérés par commune au lieu de 1 040). Les réponses de l'API sont gardées en cache 30 jours.

**Point ouvert (27/11)** : le travail du pôle DATA utilise quatre décennies (2000-2009, 2010-2019, 2030-2039, 2040-2049). La base partagée ne peut contenir qu'une seule version de `dim_temps` : à décider ensemble avant le déploiement. L'application accepte n'importe quel nombre de périodes ; elle compare la première et la dernière. Pour information, l'API fournit bien toute l'année 2050 (vérifié le 27/11) : la limite `ANNEE_MAX = 2049` de `openmeteo_client.py` est trop prudente.

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
