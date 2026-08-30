<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Config;
use App\Core\Router;

Config::load();

$sessionConfig = Config::get('session', []);
session_name($sessionConfig['name'] ?? 'ecodiffusore_sess');
session_set_cookie_params([
    'lifetime' => $sessionConfig['lifetime'] ?? 28800,
    'path' => '/',
    'secure' => (Config::get('app_env') === 'production'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (Config::get('app_env') === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

$router = new Router();

// Site público
$router->get('/', [App\Controllers\PublicController::class, 'home']);
$router->post('/contato', [App\Controllers\PublicController::class, 'submitLead']);

// Autenticação
$router->get('/painel/login', [App\Controllers\AuthController::class, 'showLogin']);
$router->post('/painel/login', [App\Controllers\AuthController::class, 'login']);
$router->get('/painel/logout', [App\Controllers\AuthController::class, 'logout']);
$router->get('/painel/trocar-senha', [App\Controllers\AuthController::class, 'showChangePassword']);
$router->post('/painel/trocar-senha', [App\Controllers\AuthController::class, 'changePassword']);

// Painel
$router->get('/painel', [App\Controllers\DashboardController::class, 'index']);

$router->get('/painel/usuarios', [App\Controllers\UserController::class, 'index']);
$router->get('/painel/usuarios/novo', [App\Controllers\UserController::class, 'create']);
$router->post('/painel/usuarios', [App\Controllers\UserController::class, 'store']);
$router->get('/painel/usuarios/{id}/editar', [App\Controllers\UserController::class, 'edit']);
$router->post('/painel/usuarios/{id}', [App\Controllers\UserController::class, 'update']);

$router->get('/painel/leads', [App\Controllers\LeadController::class, 'index']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
