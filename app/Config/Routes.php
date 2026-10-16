<?php

use CodeIgniter\Router\RouteCollection;
use App\Controllers\Pages;

/** @var RouteCollection $routes */
$routes->get('/', [Pages::class, 'view']);
$routes->get('climate', 'Climate::climate');
$routes->get('climate/csv', 'Climate::csv');
$routes->get('climate/json', 'Climate::jsonDownload');
$routes->get('(:segment)', [Pages::class, 'view']);
