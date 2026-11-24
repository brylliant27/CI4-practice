<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Parcours utilisateur
$routes->get('/', 'Accueil::index');
$routes->get('diagnostic', 'Diagnostic::index');

// Droits RGPD du visiteur
$routes->get('mes-donnees/telecharger', 'MesDonnees::telecharger');
$routes->post('mes-donnees/effacer', 'MesDonnees::effacer');

// Pages d'information
$routes->get('a-propos', 'Pages::aPropos');
$routes->get('donnees-personnelles', 'Pages::donneesPersonnelles');

// Outil du pôle DEV : échantillons de séries au format du contrat d'interface
$routes->get('series', 'Series::index');
$routes->get('series/csv', 'Series::csv');
$routes->get('series/json', 'Series::json');
$routes->addRedirect('climate', 'series'); // ancienne adresse
