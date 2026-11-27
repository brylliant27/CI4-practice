# Contrat d'interface DEV → DATA — séries climatiques

| | |
|---|---|
| **Version** | **1.0** |
| **Date** | 24 novembre 2026 |
| **Équipe** | 04 — SAÉ 3.01 « Climat & Cultures » |
| **Produit par** | pôle DEV (`app/Libraries/SeriesClimat.php`) |
| **Utilisé par** | pôle DATA : module de calcul `calcul/module_calcul.py`, qui reçoit ces séries de l'application (format d'échange : `calcul/README.md`) |
| **Remplace** | l'échantillon de J2 (`writable/series_echantillons/`), retiré : longitude erronée (16,44 au lieu de 1,44) et dates réécrites par Excel (`10/1/2026`) |

**Frontière des rôles** : le pôle DEV livre les **séries** (une ligne par commune et par jour). Le pôle DATA en tire les **faits** (les indicateurs). Le test de ce contrat : le pôle DATA doit pouvoir calculer les jours de gel sur l'échantillon ci-dessous **sans poser de question au pôle DEV**.

---

## 1. Format d'une série

Une ligne = **une commune, un jour**. Les colonnes, dans cet ordre exact :

| Colonne | Type | Unité | Variable Open-Meteo d'origine | Peut être vide ? |
|---|---|---|---|---|
| `id_commune` | entier | identifiant de `dim_commune` | — | non |
| `date` | texte `AAAA-MM-JJ` | jour | `time` | non |
| `t_min` | décimal | °C | `temperature_2m_min` | oui |
| `t_max` | décimal | °C | `temperature_2m_max` | oui |
| `t_moy` | décimal | °C | `temperature_2m_mean` | oui |
| `precip` | décimal | mm/jour | `precipitation_sum` | oui |
| `et0` | décimal | mm/jour | `et0_fao_evapotranspiration_sum` | oui |

### Règles

1. **Dates au format ISO** `AAAA-MM-JJ` uniquement. Une autre forme (`1/10/2026`…) est refusée par le code.
2. **Valeur manquante = cellule vide** en CSV, `null` en JSON. Elle n'est **jamais remplacée par 0** : sinon un jour sans mesure deviendrait un jour de gel.
3. **CSV** : UTF-8 sans BOM, séparateur **virgule**, décimales avec un **point**, une ligne d'en-tête.
4. **Une série couvre une période continue** : un jour par ligne, dans l'ordre chronologique, années bissextiles comprises (29 février).
5. **Source unique** : Open-Meteo Climate API, modèle `MRI_AGCM3_2_S`, correction de biais par défaut d'Open-Meteo (jamais désactivée). Un seul scénario d'émissions est disponible, proche du plus pessimiste.
6. **Point de calcul** : latitude et longitude du centre de la commune, lues dans `dim_commune`.

> ⚠️ **`et0`** : le champ `et0_fao_evapotranspiration_sum` renvoie bien des millimètres (ce que la fiche d'amorçage niait). Mais les valeurs paraissent élevées : 2,01 mm le 1er janvier 2015 à Toulouse. Le retour du prof les juge nettement supérieures à un calcul de Hargreaves. La colonne est livrée, mais **elle n'est affichée nulle part** tant que le pôle DATA ne l'a pas contrôlée.

### Changements depuis le format de J2

| Avant (J2) | v1.0 | Pourquoi |
|---|---|---|
| `time`, `temperature_2m_min`… (noms de l'API) | `date`, `t_min`… | Noms du schéma en étoile (`Serie_Climat`) et du kit |
| pas d'`id_commune` | `id_commune` en 1ʳᵉ colonne | Le pôle DATA doit savoir de quelle commune vient chaque ligne |
| `rain_sum`, `shortwave_radiation_sum` | retirées | Aucun indicateur ne les utilise |
| latitude/longitude libres | commune choisie dans `dim_commune` | Plus de faute de frappe possible sur les coordonnées |

---

## 2. Échantillon réel

Produits par le code (route `/series`), **jamais retouchés à la main** :

- [`contrat-interface/echantillon-toulouse-2015.csv`](contrat-interface/echantillon-toulouse-2015.csv) : Toulouse (`id_commune` 13), du 1er janvier au 31 décembre 2015, 365 lignes, aucune valeur manquante ;
- [`contrat-interface/echantillon-toulouse-2015.json`](contrat-interface/echantillon-toulouse-2015.json) : les mêmes séries, avec les métadonnées (version du contrat, source, commune, unités).

```csv
id_commune,date,t_min,t_max,t_moy,precip,et0
13,2015-01-01,-0.1,7,3.1,0,2.01
13,2015-01-02,1.4,10.8,4.3,0,2.22
```

**Contrôle** : sur cet échantillon, avec un seuil de gel de 0 °C, on compte **8 jours de gel en 2015**. L'application affiche la même valeur pour 2015 (Toulouse × maïs grain).

> Ne pas ouvrir ni enregistrer ces fichiers avec Excel : il réécrit les dates et les décimales. Utiliser un éditeur de texte, ou dans Excel *Données → À partir d'un fichier texte/CSV*.

### Produire un autre échantillon

Application lancée (`php spark serve`), ouvrir `/series`, choisir une commune et une période (10 ans au plus), puis « Télécharger le CSV ». Ou directement :

```
http://localhost:8080/series/csv?commune=13&debut=2011-01-01&fin=2020-12-31
http://localhost:8080/series/json?commune=13&debut=2011-01-01&fin=2020-12-31
```

---

## 3. Jeu d'essais (résultats connus d'avance)

Fichier : [`../tests/_support/Data/jeu-essais-series.csv`](../tests/_support/Data/jeu-essais-series.csv), au format du contrat, fabriqué à la main pour couvrir les cas limites. Commune fictive 99. Culture de test : seuil de gel **0 °C**, stress thermique **35 °C**, température de base **6 °C**.

| Date | t_min | t_max | t_moy | Cas testé |
|---|---|---|---|---|
| 2015-01-01 | -5 | 2 | -1,5 | gel nominal |
| 2015-01-02 | -1 | 6 | 2,5 | gel nominal |
| 2015-01-03 | **0** | 8 | 4 | **journée exactement au seuil de gel** → ne compte pas (règle stricte `<`) |
| 2015-01-04 | 0,5 | 9 | 4,8 | juste au-dessus du seuil → ne compte pas |
| 2015-01-05 | *vide* | 7 | *vide* | **valeur manquante** → ignorée, jamais comptée comme gel |
| 2015-07-01 | 18 | 38 | 28 | stress thermique |
| 2015-07-02 | 16 | **35** | 25,5 | **journée exactement au seuil de chaleur** → compte |
| 2015-07-03 | 15 | 34,9 | 25 | juste sous le seuil → ne compte pas |
| 2016-01-01 / 07-01 | 2 / 15 | 10 / 30 | 6 / 22,5 | **année sans gel** → 0, compte dans la moyenne |
| 2045-01-01 | -2 | 5 | 1,5 | gel à l'horizon 2050 |
| 2048-02-29 | -0,1 | 7 | 3,5 | **année bissextile** |

### Résultats attendus

| Indicateur | 2015 | 2016 | Moyenne 2011-2020 | Moyenne 2041-2050 |
|---|---|---|---|---|
| Jours de gel (`t_min < 0`) | 2 (1 manquant) | 0 | **1,0** | **1,0** |
| Jours de stress (`t_max ≥ 35`) | 2 | 0 | **1,0** | **0,5** |
| GDD (`Σ max(0, (t_max + t_min) / 2 − 6)`) | 60,5 (1 manquant) | 16,5 | **38,5** | **12,0** |
| Pluie (`Σ precip`) | 11,2 | 4,0 | **7,6** | **1,5** |

Les années sans aucune donnée (2011-2014, 2017-2020…) sont **exclues** de la moyenne. Une année avec des données mais sans gel (2016) **compte pour 0**.

Ces résultats sont vérifiés automatiquement des deux côtés :

- **pôle DATA** : `python calcul/test_module_calcul.py` ;
- **pôle DEV** : `tests/unit/ModuleCalculTest.php` (`php vendor/bin/phpunit`), qui envoie les mêmes séries au vrai module Python.

Si on remplace `<` par `≤` dans la règle du gel, les deux jeux d'essais échouent : un test qui peut échouer teste vraiment quelque chose.

---

## 4. Décisions de l'équipe associées

Elles sont détaillées dans [`decisions.md`](decisions.md) :

- **journée au seuil** : règles du pôle DATA, `<` pour le gel et `≥` pour la chaleur (D2) ;
- **valeur manquante** : journée ignorée et signalée, jamais remplacée par 0 ;
- **horizons** : 2011-2020 (référence) et 2041-2050 (projection), 10 ans chacun (D4, point ouvert avec le pôle DATA).

## 5. Historique

| Version | Date | Changement |
|---|---|---|
| 1.0 | 2026-11-24 | Première version versionnée : colonnes du schéma en étoile, `id_commune`, dates ISO, valeurs manquantes vides, échantillon réel régénéré, jeu d'essais. |
| 1.0 (révision) | 2026-11-27 | **Format des séries inchangé.** Les séries sont désormais transmises au module de calcul Python du pôle DATA. Résultats attendus du jeu d'essais recalculés avec les règles du pôle DATA : gel strict (2015 : 3 → 2 jours, moyenne 2011-2020 : 1,5 → 1,0), GDD à partir de `(t_max + t_min) / 2`. |

Toute modification du format passe par une nouvelle version de ce document (1.1, 2.0…), annoncée aux deux pôles, et par la mise à jour de `SeriesClimat::VERSION_CONTRAT`.
