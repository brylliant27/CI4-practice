<?php

use CodeIgniter\Router\RouteCollection;
use App\Controllers\Pages;

/** @var RouteCollection $routes */
$routes->get('/', [Pages::class, 'view']);
$routes->get('pages', [Pages::class, 'view']);
$routes->get('(:segment)', [Pages::class, 'view']);
