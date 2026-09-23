<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth;
use App\Database;
use App\Router;

$envFile = getenv('APP_ENV') === 'testing' ? '.env.testing' : '.env';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..', $envFile);
$dotenv->load();

$router = new Router();
$pdo = Database::connect();
$auth = new Auth($pdo);

$router->get('/', function () {
    echo "root";
});

$router->post('/register', function () use ($auth) {
    $auth->register();
}) ;

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);


// echo '<pre>';
// print_r($_SERVER);
// echo '</pre>';
