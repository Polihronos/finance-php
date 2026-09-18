<?php

require_once __DIR__ . '/../src/Router.php';

$router = new Router();

$router->get('/', function () {
    echo "root";
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

// echo '<pre>';
// print_r($_SERVER);
// echo '</pre>';
