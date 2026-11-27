"""
Module de calcul du pôle DATA, appelé par l'application PHP (pôle DEV).

Contrat d'échange (voir calcul/README.md) :
  - le PHP écrit sur l'entrée standard un JSON :
      {"culture": {ligne de dim_culture},
       "periodes": [{"id_temps": 1, "annee_debut": 2011, "annee_fin": 2020,
                     "series": [{"id_commune": 13, "date": "2011-01-01", "t_min": -1.2,
                                 "t_max": 8.4, "t_moy": 3.5, "precip": 0.0, "et0": 0.6}, ...]}]}
    Les séries suivent le contrat d'interface v1.0 (colonnes t_min, t_max, t_moy, precip, et0).
  - le module répond sur la sortie standard par UNE ligne JSON :
      {"statut": "ok", "indicateurs": {code: {"libelle", "unite", "calculable", "message",
                                              "periodes": {id_temps: {"moyenne", "annees"}}}}}
    ou, si l'entrée est inutilisable : {"statut": "erreur", "message": "..."}

Le module ne touche ni à la base ni à l'API : il reçoit des séries, renvoie des nombres.
C'est le PHP qui enregistre les faits dans fact_indicateur, rattachés à la session du visiteur.

Pas de print() ailleurs que dans repondre() : tout ce qui sort sur stdout part vers le PHP.
Pour déboguer : print(..., file=sys.stderr).
"""
import json
import sys

from indicateurs import INDICATEURS

# Colonne du contrat d'interface -> nom de la variable Open-Meteo lu par indicateurs.py
# (même correspondance que SeriesClimat::COLONNES côté PHP)
CONTRAT_VERS_OPENMETEO = {
    "t_min": "temperature_2m_min",
    "t_max": "temperature_2m_max",
    "t_moy": "temperature_2m_mean",
    "precip": "precipitation_sum",
    "et0": "et0_fao_evapotranspiration_sum",
}


def jour_depuis_contrat(ligne):
    """Une ligne du contrat -> le dictionnaire que reçoivent les fonctions de indicateurs.py."""
    jour = {"date": ligne["date"]}
    for colonne, variable in CONTRAT_VERS_OPENMETEO.items():
        jour[variable] = ligne.get(colonne)  # colonne absente = donnée manquante (None)
    return jour


def ranger_par_annee(series, annee_debut, annee_fin):
    """{2011: [jours de 2011], ...} pour TOUTES les années de la période, même vides."""
    par_annee = {annee: [] for annee in range(annee_debut, annee_fin + 1)}
    for ligne in series:
        annee = int(ligne["date"][:4])
        if annee in par_annee:  # un jour hors période est ignoré
            par_annee[annee].append(jour_depuis_contrat(ligne))
    return par_annee


def compter_manquants(jours, variables):
    """Nombre de jours où au moins une des variables de l'indicateur manque."""
    return sum(1 for jour in jours if any(jour[v] is None for v in variables))


def calculer_periode(indicateur, jours_par_annee, culture):
    """Une valeur par année, puis la moyenne des années qui ont des données.
    Une année sans AUCUN jour exploitable n'a pas de valeur (None) : elle est exclue de la
    moyenne au lieu de compter pour 0 (sinon une panne de l'API ferait baisser la moyenne)."""
    variables = indicateur["variables"]
    annees = {}
    for annee, jours in jours_par_annee.items():
        manquants = compter_manquants(jours, variables)
        if len(jours) - manquants == 0:
            valeur = None
        else:
            valeur = round(float(indicateur["fonction"](jours, culture)), 1)
        annees[str(annee)] = {"valeur": valeur, "manquants": manquants}

    valeurs = [a["valeur"] for a in annees.values() if a["valeur"] is not None]
    moyenne = round(sum(valeurs) / len(valeurs), 1) if valeurs else None
    return {"moyenne": moyenne, "annees": annees}


def calculer(demande):
    culture = demande["culture"]
    periodes = demande["periodes"]

    resultat = {}
    for code, indicateur in INDICATEURS.items():
        sortie = {"libelle": indicateur["libelle"], "unite": indicateur["unite"],
                  "calculable": True, "message": None, "periodes": {}}
        try:
            for p in periodes:
                jours_par_annee = ranger_par_annee(p["series"], int(p["annee_debut"]), int(p["annee_fin"]))
                sortie["periodes"][str(p["id_temps"])] = calculer_periode(indicateur, jours_par_annee, culture)
        except ValueError as e:
            # seuil absent dans dim_culture : on n'invente pas de valeur, les autres indicateurs continuent
            sortie = {**sortie, "calculable": False, "message": str(e), "periodes": {}}
        resultat[code] = sortie
    return resultat


def repondre(donnees):
    # ensure_ascii (par défaut) : la sortie est en ASCII pur, quel que soit l'encodage de la console
    print(json.dumps(donnees))


def main():
    try:
        demande = json.loads(sys.stdin.buffer.read().decode("utf-8"))
        if not isinstance(demande, dict) or "culture" not in demande or "periodes" not in demande:
            raise ValueError("entrée attendue : {\"culture\": ..., \"periodes\": [...]}")
        indicateurs = calculer(demande)
    except Exception as e:  # toute erreur devient un JSON lisible par le PHP
        repondre({"statut": "erreur", "message": f"{type(e).__name__} : {e}"})
        return 1
    repondre({"statut": "ok", "indicateurs": indicateurs})
    return 0


if __name__ == "__main__":
    sys.exit(main())
