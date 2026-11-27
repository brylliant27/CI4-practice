# Déployer l'application sur Alwaysdata (équipe 04)

Procédure pour mettre en ligne l'application CodeIgniter 4 et le module de calcul Python du pôle DATA, puis pour la redéployer. Elle sert aussi de procédure écrite pour le critère C5 (déploiement maîtrisé et reproductible).

Dans les commandes, `<compte>` est le nom du compte Alwaysdata de l'équipe.

| Élément | Valeur |
|---|---|
| Adresse du site | `https://<compte>.alwaysdata.net` |
| Connexion SSH | `ssh <compte>@ssh-<compte>.alwaysdata.net` |
| Dossier du projet sur le serveur | `~/www/sae301` |
| Racine publique du site | `~/www/sae301/public` (jamais la racine du projet : `.env` et le code seraient téléchargeables) |
| Base de données | MySQL d'Alwaysdata, accessible **uniquement depuis le serveur** (offre gratuite) |

## Premier déploiement

### 1. Se connecter en SSH et vérifier la place disponible

```bash
ssh <compte>@ssh-<compte>.alwaysdata.net
du -sh ~          # l'offre gratuite donne 100 Mo
```

Si la connexion est refusée, autoriser la connexion par mot de passe dans l'administration : *Accès distant → SSH*.

### 2. Récupérer le code

```bash
cd ~/www
git clone https://github.com/brylliant27/CI4-practice.git sae301
cd sae301
composer install --no-dev --optimize-autoloader
du -sh ~          # vérifier qu'on reste sous 100 Mo
```

Le dossier `vendor/` n'est pas dans Git : `composer install` le reconstruit, sans les outils de test (`--no-dev`).

### 3. Vérifier Python

Le module de calcul n'a besoin que de la bibliothèque standard : aucun `pip install`, pas d'environnement virtuel.

```bash
command -v python     # chemin absolu, à recopier dans .env (étape 4)
python --version      # 3.8 ou plus
```

On met le **chemin absolu** dans `.env` parce que le PHP du site web n'a pas forcément le même `PATH` que la session SSH.

### 4. Écrire le `.env` de production (sur le serveur uniquement)

```bash
cp .env.example .env
nano .env
chmod 600 .env
```

Valeurs à renseigner :
- `<compte>` ;
- le mot de passe de la base (*Bases de données → MySQL → Utilisateurs*) ;
- la clé Open-Meteo ;
- dans `calcul.python`, le chemin donné à l'étape 3.

⚠️ L'hôte s'écrit `mysql-<compte>.alwaysdata.net`, **sans point final**.

Le `.env` ne passe jamais par Git ni par la messagerie de l'équipe.

### 5. Créer les tables et remplir les référentiels

⚠️ La base est **partagée avec le pôle DATA**. Avant de lancer quoi que ce soit, regarder dans phpMyAdmin (*Bases de données → MySQL → phpMyAdmin*) quelles tables existent déjà.

- **Base vide :**
  ```bash
  php spark migrate
  php spark db:seed ReferentielSeeder
  ```
- **Tables déjà créées par le script SQL du pôle DATA :** ne pas lancer `migrate`, qui échouerait sur « table already exists ». Les deux schémas sont compatibles : mêmes tables, même clé de `fact_indicateur`. Vérifier seulement avec DATA :
  - que les référentiels sont remplis ;
  - quelles périodes contient `dim_temps` (l'application attend une période de référence et une période d'horizon).

### 6. Vérifier le serveur

```bash
php spark deploiement:verifier
```

Chaque ligne teste une seule chose : PHP et ses extensions, droits d'écriture, `proc_open`, module Python (sur le jeu d'essais, avec le résultat attendu), base de données, API Open-Meteo. **Toutes les lignes doivent être vertes** avant de continuer.

### 7. Déclarer le site

Dans l'administration : *Web → Sites*. **Modifier le site existant** de l'adresse `<compte>.alwaysdata.net` (ne pas en créer un second) :

| Champ | Valeur |
|---|---|
| Type | PHP |
| Répertoire racine | `/www/sae301/public/` |
| Version de PHP | 8.2 ou plus (la même qu'en local) |

Puis **redémarrer le site**. Un changement de configuration ne prend pas effet sans redémarrage.

### 8. Tester en ligne, depuis un autre appareil

Faire le test depuis le téléphone d'un équipier, pas depuis le poste qui a déployé :

1. Ouvrir `https://<compte>.alwaysdata.net` : la page d'accueil s'affiche, en HTTPS.
2. Lancer le diagnostic Toulouse × Maïs grain : les 4 indicateurs s'affichent.
3. *Télécharger mes données* : un CSV avec les lignes du diagnostic.
4. *Effacer mes données*, puis télécharger à nouveau : le CSV est vide.

## Redéployer (à chaque étape stable, pas à chaque modification)

```bash
ssh <compte>@ssh-<compte>.alwaysdata.net
cd ~/www/sae301
git pull
composer install --no-dev --optimize-autoloader
php spark migrate              # seulement si une migration a été ajoutée
php spark deploiement:verifier
```

Puis redémarrer le site dans l'administration et refaire le test de l'étape 8.

Une seule personne redéploie à la fois, et seulement depuis `main`.

## Quand ça ne marche pas

Commencer par lire les journaux :
- **erreurs de l'application :** `~/www/sae301/writable/logs/log-AAAA-MM-JJ.log` ;
- **erreurs du serveur web :** `~/admin/logs/`.

| Symptôme | Cause la plus probable |
|---|---|
| Page par défaut d'Alwaysdata | Le site ne pointe pas sur `/www/sae301/public/`, ou il n'a pas été redémarré |
| Erreur 500, « Whoops! » | Lire `writable/logs/` : `.env` absent, extension PHP manquante, droits sur `writable/` |
| « Unable to connect to the database » | Valeurs `database.default.*` du `.env` (hôte sans point final), ou mot de passe de l'utilisateur MySQL |
| « Module de calcul : impossible de lancer… » | `calcul.python` n'est pas un chemin absolu valide (refaire `command -v python`) |
| « Module de calcul : réponse illisible » | Erreur Python ou `print()` oublié dans `calcul/indicateurs.py` : le message de Python est dans `writable/logs/` |
| « Module de calcul : délai de 30 s dépassé » | Serveur surchargé : relancer, et vérifier que le module répond en SSH |
| Erreur Open-Meteo 429 | Quota dépassé : la clé `OPENMETEO_API_KEY` est-elle renseignée ? Le cache dure 30 jours |
| Le diagnostic n'est pas gardé d'une page à l'autre | Le site est ouvert en `http://` alors que `cookie.secure = true` : utiliser `https://` |
| La correction ne prend pas effet | Site non redémarré, ou `git pull` fait dans un autre dossier |
