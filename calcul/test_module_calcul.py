"""
Jeu d'essais du module de calcul, sans API ni base.

Séries : tests/_support/Data/jeu-essais-series.csv (format du contrat d'interface v1.0),
le même fichier que les tests PHP. Résultats attendus calculés à la main ci-dessous.
Culture de test : seuil de gel 0 °C, stress thermique 35 °C, température de base 6 °C.

Lancer :  python calcul/test_module_calcul.py   (depuis la racine du dépôt)
"""
import csv
import json
import os
import subprocess
import sys
import unittest

DOSSIER = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, DOSSIER)

from module_calcul import calculer  # noqa: E402

CSV = os.path.join(DOSSIER, "..", "tests", "_support", "Data", "jeu-essais-series.csv")
CULTURE = {"nom": "Culture de test", "seuil_gel_c": 0.0, "seuil_stress_thermique_c": 35.0, "t_base_c": 6.0}


def lire_series():
    series = []
    with open(CSV, encoding="utf-8") as f:
        for ligne in csv.DictReader(f):
            series.append({k: (v if k == "date" else (None if v == "" else float(v))) for k, v in ligne.items()})
    return series


def demande(culture=CULTURE):
    series = lire_series()
    return {"culture": culture, "periodes": [
        {"id_temps": 1, "annee_debut": 2011, "annee_fin": 2020, "series": series},
        {"id_temps": 2, "annee_debut": 2041, "annee_fin": 2050, "series": series},
    ]}


class TestModuleCalcul(unittest.TestCase):
    def setUp(self):
        self.res = calculer(demande())

    def test_jours_de_gel(self):
        gel = self.res["jours_gel"]["periodes"]
        # 2015 : -5 oui, -1 oui, 0 NON (règle stricte t_min < seuil), 0,5 non, 1 jour sans t_min
        self.assertEqual(gel["1"]["annees"]["2015"], {"valeur": 2.0, "manquants": 1})
        # 2016 : année sans gel -> 0 (ce n'est pas une donnée manquante)
        self.assertEqual(gel["1"]["annees"]["2016"]["valeur"], 0.0)
        # 2011 : aucune donnée -> pas de valeur, exclue de la moyenne
        self.assertIsNone(gel["1"]["annees"]["2011"]["valeur"])
        self.assertEqual(gel["1"]["moyenne"], 1.0)  # (2 + 0) / 2
        self.assertEqual(gel["2"]["moyenne"], 1.0)  # 2045 : 1, 2048 : 1
        self.assertEqual(len(gel["1"]["annees"]), 10)

    def test_stress_thermique(self):
        stress = self.res["jours_stress"]["periodes"]
        # 2015 : 38 oui, 35 oui (t_max >= seuil), 34,9 non
        self.assertEqual(stress["1"]["annees"]["2015"]["valeur"], 2.0)
        self.assertEqual(stress["1"]["moyenne"], 1.0)
        self.assertEqual(stress["2"]["moyenne"], 0.5)

    def test_gdd(self):
        gdd = self.res["gdd"]["periodes"]
        # 2015 : juillet (18+38)/2-6 = 22 ; (16+35)/2-6 = 19,5 ; (15+34,9)/2-6 = 18,95
        self.assertAlmostEqual(gdd["1"]["annees"]["2015"]["valeur"], 60.45, delta=0.06)
        # 2016 : (2+10)/2 = 6 n'est pas > 6 -> 0 ; (15+30)/2-6 = 16,5
        self.assertEqual(gdd["1"]["annees"]["2016"]["valeur"], 16.5)
        self.assertEqual(gdd["2"]["moyenne"], 12.0)  # 2045 : 24, 2048 : 0

    def test_cumul_pluie(self):
        pluie = self.res["cumul_pluie"]["periodes"]
        self.assertEqual(pluie["1"]["moyenne"], 7.6)  # (11,2 + 4) / 2
        self.assertEqual(pluie["2"]["moyenne"], 1.5)  # (2 + 1) / 2

    def test_seuil_absent_rend_l_indicateur_non_calculable(self):
        res = calculer(demande({**CULTURE, "seuil_gel_c": None}))
        self.assertFalse(res["jours_gel"]["calculable"])
        self.assertIn("seuil_gel_c", res["jours_gel"]["message"])
        self.assertTrue(res["cumul_pluie"]["calculable"])  # les autres continuent

    def test_appel_comme_le_php(self):
        """Le module lancé en sous-processus : JSON sur stdin, une ligne JSON sur stdout."""
        script = os.path.join(DOSSIER, "module_calcul.py")
        ok = subprocess.run([sys.executable, script], input=json.dumps(demande()).encode(),
                            capture_output=True)
        self.assertEqual(ok.returncode, 0)
        self.assertEqual(json.loads(ok.stdout)["statut"], "ok")

        ko = subprocess.run([sys.executable, script], input=b"pas du json", capture_output=True)
        self.assertEqual(ko.returncode, 1)
        self.assertEqual(json.loads(ko.stdout)["statut"], "erreur")


if __name__ == "__main__":
    unittest.main()
