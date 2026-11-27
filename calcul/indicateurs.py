"""
INDICATEURS : le SEUL fichier à modifier pour ajouter un indicateur.

Pour ajouter un indicateur :
  1. écrire une fonction  calcul_xxx(jours, culture)  qui renvoie UN nombre pour UNE année
       - jours   : liste des jours de l'année, chaque jour est un dictionnaire
                   {"date": "2030-07-14", "temperature_2m_max": 31.2, ...}
                   (il contient les variables déclarées dans INDICATEURS)
       - culture : la ligne de dim_culture (dictionnaire {nom_colonne: valeur})
  2. l'ajouter dans le dictionnaire INDICATEURS, en bas du fichier
Le script fait ensuite la moyenne des années sur chaque période et enregistre le résultat.

Noms des variables : ceux de l'API Open-Meteo Climate (rubrique "Daily Weather Variables"),
ex. temperature_2m_max, temperature_2m_min, precipitation_sum...

Propriétaire : pôle DATA. Fichier repris de indicateur_bdd_api_test/indicateurs.py.
Les trois fonctions marquées « PROPOSITION DEV » ont été portées depuis les requêtes SQL
du dossier J2 (SQL_CALC_JOURS_GEL, SQL_CALC_GDD, SQL_CALC_PRECIP_ANNUELLES), avec les
mêmes règles. Elles restent à relire et valider par le pôle DATA.
"""

def seuil_culture(culture, colonne):
    """Lit un seuil dans dim_culture. Arrête avec un message clair s'il est NULL ou si la colonne n'existe pas."""
    valeur = culture.get(colonne)  # get() renvoie None si la colonne n'existe pas (au lieu de planter)
    if valeur is None:
        raise ValueError(f"La culture {culture['nom']} n'a pas de valeur pour « {colonne} » (NULL ou colonne absente)")
    return float(valeur)  # DECIMAL MySQL -> nombre à virgule Python


# Jours de stress thermique
def calcul_jours_stress(jours, culture):
    """Nombre de jours de l'année où la température max atteint ou dépasse le seuil de la culture."""
    seuil = seuil_culture(culture, "seuil_stress_thermique_c")
    nb = 0
    for jour in jours:
        t_max = jour["temperature_2m_max"]
        if t_max is not None and t_max >= seuil:  # None = donnée manquante, on ne la compte pas
            nb += 1
    return nb


# Jours de gel (PROPOSITION DEV, à valider par DATA)
def calcul_jours_gel(jours, culture):
    """Nombre de jours de l'année où la température min est STRICTEMENT sous le seuil de gel
    de la culture (même règle que SQL_CALC_JOURS_GEL : t_min < seuil_gel_c)."""
    seuil = seuil_culture(culture, "seuil_gel_c")
    nb = 0
    for jour in jours:
        t_min = jour["temperature_2m_min"]
        if t_min is not None and t_min < seuil:
            nb += 1
    return nb


# Cumul de chaleur GDD (PROPOSITION DEV, à valider par DATA)
def calcul_gdd(jours, culture):
    """Somme sur l'année de max(0, (t_max + t_min) / 2 - t_base_c), comme SQL_CALC_GDD.
    Un jour où t_max ou t_min manque n'apporte rien."""
    t_base = seuil_culture(culture, "t_base_c")
    total = 0
    for jour in jours:
        t_max = jour["temperature_2m_max"]
        t_min = jour["temperature_2m_min"]
        if t_max is not None and t_min is not None:
            t_moy = (t_max + t_min) / 2
            if t_moy > t_base:
                total += t_moy - t_base
    return total


# Cumul de pluie (PROPOSITION DEV, à valider par DATA)
def calcul_cumul_pluie(jours, culture):
    """Total des précipitations de l'année, en mm (comme SQL_CALC_PRECIP_ANNUELLES)."""
    total = 0
    for jour in jours:
        pluie = jour["precipitation_sum"]
        if pluie is not None:
            total += pluie
    return total


# LISTE DES INDICATEURS DISPONIBLES
# clé = code de l'indicateur (celui stocké dans fact_indicateur.code_indicateur)
INDICATEURS = {
    "jours_gel": {
        "libelle": "Jours de gel",
        "fonction": calcul_jours_gel,
        "variables": ["temperature_2m_min"],
        "unite": "jours/an",
    },
    "jours_stress": {
        "libelle": "Jours de stress thermique",   # nom lisible, pour l'affichage
        "fonction": calcul_jours_stress,           # la fonction ci-dessus (sans parenthèses)
        "variables": ["temperature_2m_max"],       # variables de l'API dont la fonction a besoin
        "unite": "jours/an",                       # unité de la valeur moyenne
    },
    "gdd": {
        "libelle": "Cumul de chaleur (GDD)",
        "fonction": calcul_gdd,
        "variables": ["temperature_2m_max", "temperature_2m_min"],
        "unite": "°C·j/an",
    },
    "cumul_pluie": {
        "libelle": "Cumul de pluie",
        "fonction": calcul_cumul_pluie,
        "variables": ["precipitation_sum"],
        "unite": "mm/an",
    },
}
