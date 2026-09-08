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
$router->get('/cidades/buscar', [App\Controllers\CityController::class, 'search']);
$router->post('/contato', [App\Controllers\PublicController::class, 'submitLead']);
$router->get('/comprar', [App\Controllers\PublicController::class, 'buy']);
$router->post('/comprar/iniciar', [App\Controllers\PublicController::class, 'startCheckout']);
$router->post('/comprar/pagamento', [App\Controllers\PublicController::class, 'checkout']);
$router->post('/comprar/buscar-placa', [App\Controllers\PublicController::class, 'lookupPlate']);
$router->post('/comprar/orcamento', [App\Controllers\PublicController::class, 'submitOrcamento']);
$router->get('/comprar/orcamento', [App\Controllers\PublicController::class, 'showOrcamento']);
$router->get('/comprar/orcamento/pdf', [App\Controllers\PublicController::class, 'downloadOrcamentoPdf']);

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
$router->post('/painel/resumo-semanal', [App\Controllers\DashboardController::class, 'toggleWeeklyDigest']);
$router->get('/painel/meus-pedidos/{id}', [App\Controllers\ClientPortalController::class, 'showOrder']);

$router->get('/painel/meus-dados', [App\Controllers\ClientProfileController::class, 'edit']);
$router->post('/painel/meus-dados', [App\Controllers\ClientProfileController::class, 'update']);
$router->get('/painel/minhas-garantias', [App\Controllers\ClientPortalController::class, 'warranties']);
$router->get('/painel/minhas-garantias/nova', [App\Controllers\ClientPortalController::class, 'newWarranty']);
$router->post('/painel/minhas-garantias', [App\Controllers\ClientPortalController::class, 'storeWarranty']);
$router->get('/painel/minhas-garantias/{id}', [App\Controllers\ClientPortalController::class, 'showWarranty']);
$router->get('/painel/minhas-garantias/{id}/anexo/{attachmentId}', [App\Controllers\ClientPortalController::class, 'downloadWarrantyAttachment']);
$router->get('/painel/minhas-garantias/{id}/termo', [App\Controllers\ClientPortalController::class, 'downloadWarrantyTerm']);

$router->get('/painel/garantias', [App\Controllers\WarrantyController::class, 'index']);
$router->get('/painel/garantias/{id}', [App\Controllers\WarrantyController::class, 'show']);
$router->post('/painel/garantias/{id}/status', [App\Controllers\WarrantyController::class, 'updateStatus']);

$router->get('/painel/fabrica', [App\Controllers\FactoryController::class, 'index']);
$router->post('/painel/fabrica/{id}/entrega', [App\Controllers\FactoryController::class, 'updateDelivery']);
$router->post('/painel/fabrica/{id}/entregue', [App\Controllers\FactoryController::class, 'markDelivered']);
$router->get('/painel/entregas', [App\Controllers\OrderController::class, 'deliveries']);
$router->get('/painel/garantias/{id}/anexo/{attachmentId}', [App\Controllers\WarrantyController::class, 'downloadAttachment']);
$router->get('/painel/garantias/{id}/termo', [App\Controllers\WarrantyController::class, 'downloadTerm']);

$router->get('/painel/usuarios', [App\Controllers\UserController::class, 'index']);
$router->get('/painel/usuarios/novo', [App\Controllers\UserController::class, 'create']);
$router->post('/painel/usuarios', [App\Controllers\UserController::class, 'store']);
$router->get('/painel/usuarios/{id}/editar', [App\Controllers\UserController::class, 'edit']);
$router->post('/painel/usuarios/excluir-lote', [App\Controllers\UserController::class, 'destroyBulk']);
$router->post('/painel/usuarios/{id}', [App\Controllers\UserController::class, 'update']);
$router->post('/painel/usuarios/{id}/excluir', [App\Controllers\UserController::class, 'destroy']);
$router->get('/painel/licenciados', [App\Controllers\UserController::class, 'licenciados']);
$router->post('/painel/licenciados/atribuir-lote', [App\Controllers\UserController::class, 'assignSupervisorBulk']);
$router->post('/painel/licenciados/{id}/supervisor', [App\Controllers\UserController::class, 'assignSupervisor']);

// Onboarding de Licenciado: perfil completo + assinatura/KYC via ClickSign
$router->get('/painel/licenciados/completar-perfil', [App\Controllers\LicenciadoOnboardingController::class, 'showProfileForm']);
$router->post('/painel/licenciados/completar-perfil', [App\Controllers\LicenciadoOnboardingController::class, 'submitProfileForm']);
$router->get('/painel/licenciados/aguardando-assinatura', [App\Controllers\LicenciadoOnboardingController::class, 'showWaitingPage']);
$router->post('/painel/licenciados/aguardando-assinatura/verificar', [App\Controllers\LicenciadoOnboardingController::class, 'refreshStatus']);
$router->get('/painel/licenciados/documento/{id}/{tipo}', [App\Controllers\LicenciadoOnboardingController::class, 'downloadDocument']);
$router->get('/painel/licenciados/contrato/{id}', [App\Controllers\LicenciadoOnboardingController::class, 'downloadContract']);

// Aprovacao manual de cadastro (Admin/Gerente), depois do ClickSign confirmar a assinatura
$router->get('/painel/licenciados/aprovacoes', [App\Controllers\LicenciadoApprovalController::class, 'index']);
$router->get('/painel/licenciados/{id}/perfil', [App\Controllers\LicenciadoApprovalController::class, 'show']);
$router->post('/painel/licenciados/{id}/aprovar', [App\Controllers\LicenciadoApprovalController::class, 'approve']);
$router->post('/painel/licenciados/{id}/reprovar', [App\Controllers\LicenciadoApprovalController::class, 'reject']);

$router->get('/painel/leads', [App\Controllers\LeadController::class, 'index']);
$router->post('/painel/leads/{id}/status', [App\Controllers\LeadController::class, 'updateStatus']);
$router->post('/painel/leads/{id}/atribuir', [App\Controllers\LeadController::class, 'assign']);
$router->post('/painel/leads/colunas', [App\Controllers\LeadController::class, 'addStage']);
$router->post('/painel/leads/{id}/excluir', [App\Controllers\LeadController::class, 'destroy']);
$router->post('/painel/leads/{id}/estender', [App\Controllers\LeadController::class, 'requestExtension']);
$router->post('/painel/leads/{id}/notas', [App\Controllers\LeadController::class, 'storeNote']);
$router->get('/painel/leads/notas/{noteId}/anexo', [App\Controllers\LeadController::class, 'downloadNoteAttachment']);
$router->get('/painel/leads/extensoes', [App\Controllers\LeadExtensionController::class, 'index']);
$router->get('/painel/leads/extensoes/{id}/anexo', [App\Controllers\LeadExtensionController::class, 'downloadAttachment']);

$router->get('/painel/simulador', [App\Controllers\SimuladorController::class, 'index']);
$router->post('/painel/simulador', [App\Controllers\SimuladorController::class, 'calcular']);
$router->post('/painel/simulador/pdf', [App\Controllers\SimuladorController::class, 'downloadPdf']);

$router->get('/painel/materiais', [App\Controllers\MaterialController::class, 'index']);
$router->post('/painel/materiais/scripts', [App\Controllers\MaterialController::class, 'storeScript']);
$router->post('/painel/materiais/scripts/{id}/excluir', [App\Controllers\MaterialController::class, 'deleteScript']);
$router->post('/painel/materiais/depoimentos', [App\Controllers\MaterialController::class, 'storeTestimonial']);
$router->post('/painel/materiais/depoimentos/{id}/excluir', [App\Controllers\MaterialController::class, 'deleteTestimonial']);

// Clientes
$router->get('/painel/clientes', [App\Controllers\ClientController::class, 'index']);
$router->get('/painel/clientes/novo', [App\Controllers\ClientController::class, 'create']);
$router->post('/painel/clientes', [App\Controllers\ClientController::class, 'store']);
$router->post('/painel/clientes/vincular-vendedor', [App\Controllers\ClientController::class, 'bulkAssignSeller']);
$router->post('/painel/clientes/excluir-lote', [App\Controllers\ClientController::class, 'destroyBulk']);
$router->get('/painel/clientes/exportar', [App\Controllers\ClientController::class, 'export']);
$router->get('/painel/clientes/{id}/editar', [App\Controllers\ClientController::class, 'edit']);
$router->post('/painel/clientes/{id}', [App\Controllers\ClientController::class, 'update']);
$router->post('/painel/clientes/{id}/excluir', [App\Controllers\ClientController::class, 'destroy']);
$router->get('/painel/clientes/{id}', [App\Controllers\ClientController::class, 'show']);
$router->post('/painel/clientes/{id}/notas', [App\Controllers\ClientController::class, 'storeNote']);
$router->get('/painel/clientes/notas/{id}/anexo', [App\Controllers\ClientController::class, 'downloadNoteAttachment']);
$router->post('/painel/clientes/{id}/criar-acesso', [App\Controllers\ClientController::class, 'createAccess']);

// Produtos
$router->get('/painel/produtos', [App\Controllers\ProductController::class, 'index']);
$router->get('/painel/produtos/novo', [App\Controllers\ProductController::class, 'create']);
$router->post('/painel/produtos', [App\Controllers\ProductController::class, 'store']);
$router->get('/painel/produtos/{id}/editar', [App\Controllers\ProductController::class, 'edit']);
$router->post('/painel/produtos/{id}', [App\Controllers\ProductController::class, 'update']);

$router->get('/painel/tabela-precos', [App\Controllers\PricingTierController::class, 'index']);
$router->post('/painel/tabela-precos', [App\Controllers\PricingTierController::class, 'store']);
$router->get('/painel/tabela-precos/{id}/editar', [App\Controllers\PricingTierController::class, 'edit']);
$router->post('/painel/tabela-precos/{id}', [App\Controllers\PricingTierController::class, 'update']);
$router->post('/painel/tabela-precos/{id}/excluir', [App\Controllers\PricingTierController::class, 'destroy']);

$router->get('/painel/configuracoes/pagamento', [App\Controllers\PaymentSettingsController::class, 'index']);
$router->post('/painel/configuracoes/pagamento', [App\Controllers\PaymentSettingsController::class, 'update']);

$router->get('/painel/configuracoes/nfe', [App\Controllers\NfeSettingsController::class, 'index']);
$router->post('/painel/configuracoes/nfe', [App\Controllers\NfeSettingsController::class, 'update']);
$router->get('/painel/configuracoes/nfe/buscar-servico', [App\Controllers\NfeSettingsController::class, 'searchService']);

$router->get('/painel/configuracoes/email', [App\Controllers\EmailTemplateSettingsController::class, 'index']);
$router->post('/painel/configuracoes/email', [App\Controllers\EmailTemplateSettingsController::class, 'update']);
$router->post('/painel/configuracoes/email/evento/{eventKey}', [App\Controllers\EmailTemplateSettingsController::class, 'updateEvent']);

$router->get('/painel/configuracoes/roteamento', [App\Controllers\LeadRoutingSettingsController::class, 'index']);
$router->post('/painel/configuracoes/roteamento', [App\Controllers\LeadRoutingSettingsController::class, 'update']);

$router->get('/painel/configuracoes/whatsapp', [App\Controllers\WhatsAppSettingsController::class, 'index']);
$router->get('/painel/configuracoes/whatsapp/status', [App\Controllers\WhatsAppSettingsController::class, 'status']);
$router->post('/painel/configuracoes/whatsapp/desconectar', [App\Controllers\WhatsAppSettingsController::class, 'disconnect']);
$router->post('/painel/configuracoes/whatsapp/evento/{eventKey}', [App\Controllers\WhatsAppSettingsController::class, 'updateTemplate']);

// Pedidos
$router->get('/painel/pedidos', [App\Controllers\OrderController::class, 'index']);
$router->get('/painel/pedidos/novo', [App\Controllers\OrderController::class, 'create']);
$router->post('/painel/pedidos', [App\Controllers\OrderController::class, 'store']);
$router->get('/painel/pedidos/exportar', [App\Controllers\OrderController::class, 'export']);
$router->get('/painel/pedidos/{id}', [App\Controllers\OrderController::class, 'show']);
$router->get('/painel/pedidos/{id}/editar', [App\Controllers\OrderController::class, 'edit']);
$router->post('/painel/pedidos/{id}', [App\Controllers\OrderController::class, 'update']);
$router->post('/painel/pedidos/{id}/status', [App\Controllers\OrderController::class, 'markStatus']);
$router->post('/painel/pedidos/{id}/reembolsar', [App\Controllers\OrderController::class, 'refundPayment']);
$router->post('/painel/pedidos/{id}/rastreio', [App\Controllers\OrderController::class, 'updateTracking']);
$router->get('/painel/pedidos/{id}/documento-veiculo', [App\Controllers\OrderController::class, 'downloadVehicleDocument']);
$router->post('/painel/pedidos/{id}/cobranca', [App\Controllers\PaymentController::class, 'generateForOrder']);

// Orcamentos
$router->get('/painel/orcamentos', [App\Controllers\QuoteController::class, 'index']);
$router->get('/painel/orcamentos/kanban', [App\Controllers\QuoteController::class, 'kanban']);
$router->get('/painel/orcamentos/novo', [App\Controllers\QuoteController::class, 'create']);
$router->post('/painel/orcamentos', [App\Controllers\QuoteController::class, 'store']);
$router->get('/painel/orcamentos/{id}', [App\Controllers\QuoteController::class, 'show']);
$router->get('/painel/orcamentos/{id}/editar', [App\Controllers\QuoteController::class, 'edit']);
$router->post('/painel/orcamentos/{id}', [App\Controllers\QuoteController::class, 'update']);
$router->post('/painel/orcamentos/{id}/status', [App\Controllers\QuoteController::class, 'markStatus']);
$router->post('/painel/orcamentos/{id}/converter', [App\Controllers\QuoteController::class, 'convert']);
$router->post('/painel/orcamentos/{id}/cobranca', [App\Controllers\PaymentController::class, 'generateForQuote']);

// Proposta Facil
$router->get('/painel/proposta-facil', [App\Controllers\PropostaController::class, 'create']);
$router->post('/painel/proposta-facil', [App\Controllers\PropostaController::class, 'store']);
$router->get('/painel/proposta-facil/resultado', [App\Controllers\PropostaController::class, 'show']);
$router->get('/painel/proposta-facil/pdf', [App\Controllers\PropostaController::class, 'pdf']);
$router->post('/painel/proposta-facil/concluir', [App\Controllers\PropostaController::class, 'conclude']);

$router->get('/painel/auditoria', [App\Controllers\AuditController::class, 'index']);

// Aprovacao de desconto
$router->post('/painel/aprovacoes/{id}/decidir', [App\Controllers\ApprovalController::class, 'decide']);

// Webhook Asaas (publico)
$router->post('/webhooks/asaas', [App\Controllers\PaymentController::class, 'webhook']);

// Webhook ClickSign (publico)
$router->post('/webhooks/clicksign', [App\Controllers\ClickSignWebhookController::class, 'clicksign']);

// Busca global
$router->get('/painel/busca', [App\Controllers\SearchController::class, 'index']);

// Desempenho
$router->get('/painel/desempenho/vendedores', [App\Controllers\PerformanceController::class, 'sellers']);
$router->get('/painel/desempenho/funil', [App\Controllers\PerformanceController::class, 'funnel']);
$router->get('/painel/meu-ranking', [App\Controllers\PerformanceController::class, 'myRanking']);
$router->get('/painel/desempenho/equipe', [App\Controllers\PerformanceController::class, 'team']);
$router->get('/painel/desempenho/panorama', [App\Controllers\PerformanceController::class, 'panorama']);
$router->get('/painel/desempenho/panorama/estado/{uf}', [App\Controllers\PerformanceController::class, 'stateDetail']);

// Metas
$router->get('/painel/metas', [App\Controllers\GoalController::class, 'index']);
$router->post('/painel/metas', [App\Controllers\GoalController::class, 'store']);
$router->post('/painel/metas/{id}/excluir', [App\Controllers\GoalController::class, 'delete']);
$router->post('/painel/metas/{id}/premio-pago', [App\Controllers\GoalController::class, 'markRewardPaid']);

// Financeiro
$router->get('/painel/financeiro/caixas-bancos', [App\Controllers\FinanceController::class, 'accounts']);
$router->post('/painel/financeiro/caixas-bancos/contas', [App\Controllers\FinanceController::class, 'storeAccount']);
$router->post('/painel/financeiro/caixas-bancos/{id}/padrao', [App\Controllers\FinanceController::class, 'setDefaultAccount']);
$router->post('/painel/financeiro/transferencia', [App\Controllers\FinanceController::class, 'storeTransfer']);
$router->post('/painel/financeiro/lancamentos', [App\Controllers\FinanceController::class, 'storeTransaction']);
$router->get('/painel/financeiro/contas-a-pagar', [App\Controllers\FinanceController::class, 'payable']);
$router->get('/painel/financeiro/contas-a-receber', [App\Controllers\FinanceController::class, 'receivable']);
$router->post('/painel/financeiro/contas', [App\Controllers\FinanceController::class, 'storePayable']);
$router->get('/painel/financeiro/contas/{id}/editar', [App\Controllers\FinanceController::class, 'editTransaction']);
$router->post('/painel/financeiro/contas/{id}/excluir', [App\Controllers\FinanceController::class, 'destroyTransaction']);
$router->post('/painel/financeiro/contas/{id}/baixar', [App\Controllers\FinanceController::class, 'markPaid']);
$router->post('/painel/financeiro/contas/{id}', [App\Controllers\FinanceController::class, 'updateTransaction']);
$router->get('/painel/financeiro/anexos/{id}', [App\Controllers\FinanceController::class, 'downloadAttachment']);
$router->get('/painel/financeiro/comissoes', [App\Controllers\FinanceController::class, 'commissions']);
$router->get('/painel/financeiro/comissoes/exportar', [App\Controllers\FinanceController::class, 'exportCommissions']);
$router->post('/painel/financeiro/comissoes/{id}/baixar', [App\Controllers\FinanceController::class, 'markCommissionPaid']);

$router->get('/painel/financeiro/impostos', [App\Controllers\TaxController::class, 'index']);

$router->get('/painel/financeiro/antecipacoes', [App\Controllers\AnticipationController::class, 'index']);
$router->post('/painel/financeiro/antecipacoes/sincronizar', [App\Controllers\AnticipationController::class, 'sync']);

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
$router->get('/painel/financeiro/relatorios/{type}/pdf', [App\Controllers\ReportController::class, 'pdf']);
$router->get('/painel/financeiro/relatorios/{type}', [App\Controllers\ReportController::class, 'show']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
