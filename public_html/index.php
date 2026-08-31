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
$router->get('/painel/cadastro', [App\Controllers\AuthController::class, 'showRegister']);
$router->post('/painel/cadastro', [App\Controllers\AuthController::class, 'register']);
$router->get('/painel/esqueci-senha', [App\Controllers\AuthController::class, 'showForgotPassword']);
$router->post('/painel/esqueci-senha', [App\Controllers\AuthController::class, 'sendResetCode']);
$router->get('/painel/redefinir-senha', [App\Controllers\AuthController::class, 'showResetForm']);
$router->post('/painel/redefinir-senha', [App\Controllers\AuthController::class, 'resetPassword']);
$router->get('/painel/verificar-email', [App\Controllers\AuthController::class, 'showVerifyEmail']);
$router->post('/painel/verificar-email', [App\Controllers\AuthController::class, 'verifyEmail']);
$router->post('/painel/verificar-email/reenviar', [App\Controllers\AuthController::class, 'resendVerificationCode']);

// Painel
$router->get('/painel', [App\Controllers\DashboardController::class, 'index']);

$router->get('/painel/usuarios', [App\Controllers\UserController::class, 'index']);
$router->get('/painel/usuarios/novo', [App\Controllers\UserController::class, 'create']);
$router->post('/painel/usuarios', [App\Controllers\UserController::class, 'store']);
$router->get('/painel/usuarios/{id}/editar', [App\Controllers\UserController::class, 'edit']);
$router->post('/painel/usuarios/{id}', [App\Controllers\UserController::class, 'update']);

$router->get('/painel/leads', [App\Controllers\LeadController::class, 'index']);

// Clientes
$router->get('/painel/clientes', [App\Controllers\ClientController::class, 'index']);
$router->get('/painel/clientes/novo', [App\Controllers\ClientController::class, 'create']);
$router->post('/painel/clientes', [App\Controllers\ClientController::class, 'store']);
$router->get('/painel/clientes/{id}/editar', [App\Controllers\ClientController::class, 'edit']);
$router->post('/painel/clientes/{id}', [App\Controllers\ClientController::class, 'update']);

// Produtos
$router->get('/painel/produtos', [App\Controllers\ProductController::class, 'index']);
$router->get('/painel/produtos/novo', [App\Controllers\ProductController::class, 'create']);
$router->post('/painel/produtos', [App\Controllers\ProductController::class, 'store']);
$router->get('/painel/produtos/{id}/editar', [App\Controllers\ProductController::class, 'edit']);
$router->post('/painel/produtos/{id}', [App\Controllers\ProductController::class, 'update']);

// Pedidos
$router->get('/painel/pedidos', [App\Controllers\OrderController::class, 'index']);
$router->get('/painel/pedidos/novo', [App\Controllers\OrderController::class, 'create']);
$router->post('/painel/pedidos', [App\Controllers\OrderController::class, 'store']);
$router->get('/painel/pedidos/{id}', [App\Controllers\OrderController::class, 'show']);
$router->get('/painel/pedidos/{id}/editar', [App\Controllers\OrderController::class, 'edit']);
$router->post('/painel/pedidos/{id}', [App\Controllers\OrderController::class, 'update']);
$router->post('/painel/pedidos/{id}/status', [App\Controllers\OrderController::class, 'markStatus']);

// Desempenho
$router->get('/painel/desempenho/vendedores', [App\Controllers\PerformanceController::class, 'sellers']);

// Financeiro
$router->get('/painel/financeiro/caixas-bancos', [App\Controllers\FinanceController::class, 'accounts']);
$router->post('/painel/financeiro/caixas-bancos/contas', [App\Controllers\FinanceController::class, 'storeAccount']);
$router->post('/painel/financeiro/lancamentos', [App\Controllers\FinanceController::class, 'storeTransaction']);
$router->get('/painel/financeiro/contas-a-pagar', [App\Controllers\FinanceController::class, 'payable']);
$router->get('/painel/financeiro/contas-a-receber', [App\Controllers\FinanceController::class, 'receivable']);
$router->post('/painel/financeiro/contas', [App\Controllers\FinanceController::class, 'storePayable']);
$router->post('/painel/financeiro/contas/{id}/baixar', [App\Controllers\FinanceController::class, 'markPaid']);
$router->get('/painel/financeiro/anexos/{id}', [App\Controllers\FinanceController::class, 'downloadAttachment']);
$router->get('/painel/financeiro/comissoes', [App\Controllers\FinanceController::class, 'commissions']);
$router->post('/painel/financeiro/comissoes/{id}/baixar', [App\Controllers\FinanceController::class, 'markCommissionPaid']);

// Remessa e Retorno
$router->get('/painel/financeiro/remessas', [App\Controllers\RemittanceController::class, 'index']);
$router->get('/painel/financeiro/remessas/nova', [App\Controllers\RemittanceController::class, 'create']);
$router->post('/painel/financeiro/remessas', [App\Controllers\RemittanceController::class, 'store']);
$router->get('/painel/financeiro/remessas/{id}', [App\Controllers\RemittanceController::class, 'show']);
$router->post('/painel/financeiro/remessas/{id}/enviar', [App\Controllers\RemittanceController::class, 'send']);
$router->post('/painel/financeiro/remessas/{id}/retorno', [App\Controllers\RemittanceController::class, 'returnBack']);

// Relatorios financeiros + agendamento
$router->get('/painel/financeiro/relatorios', [App\Controllers\ReportController::class, 'index']);
$router->get('/painel/financeiro/relatorios/agendamentos', [App\Controllers\ReportController::class, 'schedules']);
$router->post('/painel/financeiro/relatorios/agendamentos', [App\Controllers\ReportController::class, 'storeSchedule']);
$router->post('/painel/financeiro/relatorios/agendamentos/{id}/excluir', [App\Controllers\ReportController::class, 'deleteSchedule']);
$router->get('/painel/financeiro/relatorios/{type}', [App\Controllers\ReportController::class, 'show']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
