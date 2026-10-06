<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth;
use App\Database;
use App\Router;
use App\ErrorHandler;
use App\Categories;

ErrorHandler::register();


$envFile = getenv('APP_ENV') === 'testing' ? '.env.testing' : '.env';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..', $envFile);
$dotenv->load();

$router = new Router();
$pdo = Database::connect();
$auth = new Auth($pdo);
$categories = new Categories($pdo);

$router->get('/', function () {
    echo "root";
});

$router->post('/register', function () use ($auth) {
    $auth->register();
});
$router->post('/login', function () use ($auth) {
    $auth->login();
});
$router->post('/logout', function () use ($auth) {
    $auth->logout();
});

$router->post('/categories', function () use ($auth, $categories) {
    $userId = $auth->requireUserId();
    if ($userId === null) {
        return;
    }
    $categories->create($userId);
});

$router->get('/categories', function () use ($auth, $categories) {
    $userId = $auth->requireUserId();
    if ($userId === null) {
        return;
    }
    $categories->list($userId);
});

$router->put('/categories/{id}', function (int $id) use ($auth, $categories) {
    $userId = $auth->requireUserId();
    if ($userId === null) {
        return;
    }
    $categories->update($userId, $id);
});

$router->delete('/categories/{id}', function (int $id) use ($auth, $categories) {
    $userId = $auth->requireUserId();
    if ($userId === null) {
        return;
    }
    $categories->delete($userId, $id);
});



$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);


// echo '<pre>';
// print_r($_SERVER);
// echo '</pre>';
