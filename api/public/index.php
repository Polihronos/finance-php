<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth;
use App\Database;
use App\Router;
use App\ErrorHandler;
use App\Categories;
use App\Transactions;

ErrorHandler::register();


$envFile = getenv('APP_ENV') === 'testing' ? '.env.testing' : '.env';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..', $envFile);
$dotenv->load();

$router = new Router();
$pdo = Database::connect();
$auth = new Auth($pdo);
$categories = new Categories($pdo);
$transactions = new Transactions($pdo);

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
    $categories->create($userId);
});

$router->get('/categories', function () use ($auth, $categories) {
    $userId = $auth->requireUserId();
    $categories->list($userId);
});

$router->put('/categories/{id}', function (int $id) use ($auth, $categories) {
    $userId = $auth->requireUserId();
    $categories->update($userId, $id);
});

$router->delete('/categories/{id}', function (int $id) use ($auth, $categories) {
    $userId = $auth->requireUserId();
    $categories->delete($userId, $id);
});

$router->get('/categories/stats', function () use ($auth, $categories) {
    $userId = $auth->requireUserId();
    $categories->stats($userId);
});

$router->post('/transactions', function () use ($auth, $transactions) {
    $userId = $auth->requireUserId();
    $transactions->create($userId);
});

$router->get('/transactions', function () use ($auth, $transactions) {
    $userId = $auth->requireUserId();
    $transactions->list($userId);
});

$router->get('/transactions/suggestions', function () use ($auth, $transactions) {
    $userId = $auth->requireUserId();
    $transactions->suggestions($userId);
});

$router->put('/transactions/{id}', function (int $id) use ($auth, $transactions) {
    $userId = $auth->requireUserId();
    $transactions->update($userId, $id);
});

$router->delete('/transactions/{id}', function (int $id) use ($auth, $transactions) {
    $userId = $auth->requireUserId();
    $transactions->delete($userId, $id);
});



$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);


// echo '<pre>';
// print_r($_SERVER);
// echo '</pre>';
