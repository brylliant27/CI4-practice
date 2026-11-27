# Module de calcul des indicateurs (pôle DATA)

Ce dossier contient le module de calcul du pôle DATA. Le pôle DATA en est propriétaire : c'est lui qui le modifie et le valide.

L'application PHP (pôle DEV) l'appelle pour chaque diagnostic :

```
ClimateApi (DEV) ─► séries du contrat v1.0 ─► module_calcul.py (DATA) ─► valeurs ─► fact_indicateur (session)
```

Le module ne se connecte ni à la base ni à l'API. Il reçoit des séries et renvoie des nombres. Il n'a besoin que de la bibliothèque standard de Python 3.

| Fichier | Rôle | Qui le modifie |
|---|---|---|
| `indicateurs.py` | Une fonction par indicateur et la liste `INDICATEURS` | DATA (c'est le seul fichier à modifier pour ajouter un indicateur) |
| `module_calcul.py` | Lit le JSON envoyé par le PHP, regroupe les jours par année, fait la moyenne par période, répond en JSON | DATA et DEV ensemble (c'est le contrat entre les deux pôles) |
| `test_module_calcul.py` | Jeu d'essais avec les résultats attendus, calculés à la main | DATA |

## Contrat d'échange entre le PHP et le Python

**Entrée** (le PHP l'écrit sur l'entrée standard) :

```json
{"culture": {"nom": "Maïs grain", "seuil_gel_c": 0, "seuil_stress_thermique_c": 35, "t_base_c": 6, "...": "..."},
 "periodes": [{"id_temps": 1, "annee_debut": 2011, "annee_fin": 2020,
               "series": [{"id_commune": 13, "date": "2011-01-01", "t_min": -1.2, "t_max": 8.4,
                           "t_moy": 3.5, "precip": 0.0, "et0": 0.6}]}]}
```

Les séries suivent le contrat d'interface v1.0 (`docs/contrat-interface.md`). Une valeur manquante vaut `null`. Le module convertit les noms de colonnes du contrat (`t_max`, etc.) en noms Open-Meteo (`temperature_2m_max`, etc.), parce que ce sont ces noms que lisent les fonctions de `indicateurs.py`.

**Sortie** (une seule ligne JSON sur la sortie standard) :

```json
{"statut": "ok", "indicateurs": {"jours_gel": {"libelle": "Jours de gel", "unite": "jours/an",
  "calculable": true, "message": null,
  "periodes": {"1": {"moyenne": 15.2, "annees": {"2011": {"valeur": 14.0, "manquants": 0}}}}}}}
```

- Si une culture n'a pas le seuil dont un indicateur a besoin, cet indicateur répond `"calculable": false` avec un `message`. Les autres indicateurs sont calculés normalement.
- Une année sans aucune donnée exploitable reçoit `"valeur": null` et n'entre pas dans la moyenne. Une année sans gel vaut `0` et entre dans la moyenne.
- Si l'entrée est inutilisable, le module répond `{"statut": "erreur", "message": "..."}` et se termine avec le code 1.

**Règle à respecter : aucun `print()` dans `indicateurs.py`.** Tout ce qui est écrit sur la sortie standard part vers le PHP et casserait le JSON. Pour déboguer, écrire sur la sortie d'erreur : `print(..., file=sys.stderr)`. Le PHP enregistre ces messages dans `writable/logs`.

## Ajouter un indicateur

Suivre la fiche « Ajouter un indicateur » en remplaçant le test console par `python calcul/test_module_calcul.py`. Un indicateur ajouté dans `INDICATEURS` apparaît automatiquement dans le tableau « Autres indicateurs » de l'application, avec son libellé et son unité. Pour une phrase d'évolution adaptée (« jours de … de plus par an »), le pôle DEV ajoute une entrée dans `app/Libraries/Indicateurs.php`.

## Tester

```
python calcul/test_module_calcul.py      # calcul seul (DATA)
php vendor/bin/phpunit                   # PHP, dont le pont PHP → Python (tests/unit/ModuleCalculTest.php)
```

## Déploiement (Alwaysdata)

Rien à installer : le module n'utilise que la bibliothèque standard. Dans le `.env` du serveur, `calcul.python` doit contenir le chemin absolu de Python, donné par `command -v python` en SSH. La procédure complète est dans `docs/deploiement.md`, et `php spark deploiement:verifier` vérifie que le module répond sur le serveur.
