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
$router->get('/sitemap.xml', [App\Controllers\PublicController::class, 'sitemap']);
$router->get('/blog', [App\Controllers\BlogController::class, 'index']);
$router->get('/blog/{slug}', [App\Controllers\BlogController::class, 'show']);
$router->get('/economia-de-diesel-parana', [App\Controllers\LocalController::class, 'parana']);
$router->get('/economia-de-diesel-goias', [App\Controllers\LocalController::class, 'goias']);
$router->get('/economia-de-diesel-mato-grosso', [App\Controllers\LocalController::class, 'matoGrosso']);
$router->get('/cidades/buscar', [App\Controllers\CityController::class, 'search']);
$router->post('/chat/mensagem', [App\Controllers\SiteChatController::class, 'message']);
$router->post('/contato', [App\Controllers\PublicController::class, 'submitLead']);
$router->get('/comprar', [App\Controllers\PublicController::class, 'buy']);
$router->post('/comprar/iniciar', [App\Controllers\PublicController::class, 'startCheckout']);
$router->post('/comprar/pagamento', [App\Controllers\PublicController::class, 'checkout']);
$router->post('/comprar/buscar-placa', [App\Controllers\PublicController::class, 'lookupPlate']);
$router->post('/comprar/orcamento', [App\Controllers\PublicController::class, 'submitOrcamento']);
$router->get('/comprar/orcamento', [App\Controllers\PublicController::class, 'showOrcamento']);
$router->get('/comprar/orcamento/pdf', [App\Controllers\PublicController::class, 'downloadOrcamentoPdf']);
$router->post('/comprar/orcamento-maquina', [App\Controllers\PublicController::class, 'submitMachineQuote']);
$router->get('/comprar/cotacao-maquina/recebida', [App\Controllers\PublicController::class, 'showMachineQuoteReceived']);

// Fase 124: versao publica (sem login) da Calculadora de Economia de Diesel, pra qualquer
// vendedor acessar direto por link, sem precisar de conta no sistema.
$router->get('/calculadora-economia-diesel', [App\Controllers\PublicLeaseCalculatorController::class, 'index']);
$router->post('/calculadora-economia-diesel', [App\Controllers\PublicLeaseCalculatorController::class, 'calcular']);
$router->post('/calculadora-economia-diesel/pdf', [App\Controllers\PublicLeaseCalculatorController::class, 'downloadPdf']);
// Fase 129: /calculadora-locacao virou /calculadora-economia-diesel (o nome antigo nao fazia mais
// sentido -- a locacao e' opcional agora). Redirect pra nao quebrar o link ja compartilhado.
$router->get('/calculadora-locacao', [App\Controllers\PublicLeaseCalculatorController::class, 'redirectLegacySlug']);

// Fase 63: link publico do Pedido (sem login) -- vendedor manda direto pro comprador.
$router->get('/pedido/{token}', [App\Controllers\PublicOrderController::class, 'show']);
$router->post('/pedido/{token}/aceitar-termos', [App\Controllers\PublicOrderController::class, 'acceptTerms']);
$router->post('/pedido/{token}/documentos', [App\Controllers\PublicOrderController::class, 'uploadDocuments']);
$router->post('/pedido/{token}/cobranca', [App\Controllers\PublicOrderController::class, 'generateCharge']);
$router->post('/pedido/{token}/cancelar-cobranca', [App\Controllers\PublicOrderController::class, 'cancelPayment']);
$router->get('/atividade-recente', [App\Controllers\PublicOrderController::class, 'recentActivity']);

$router->get('/painel/cotacoes-maquina', [App\Controllers\MachineQuoteController::class, 'index']);
$router->get('/painel/cotacoes-maquina/{id}', [App\Controllers\MachineQuoteController::class, 'show']);
$router->post('/painel/cotacoes-maquina/{id}/responder', [App\Controllers\MachineQuoteController::class, 'respond']);
$router->get('/painel/cotacoes-maquina/{id}/foto/{tipo}', [App\Controllers\MachineQuoteController::class, 'downloadPhoto']);

// Fase 76: API por token pro futuro app mobile (React Native) -- paralela ao painel web,
// nao substitui nenhuma rota existente. Ver App\Core\ApiAuth.
$router->post('/api/v1/login', [App\Controllers\Api\AuthController::class, 'login']);
$router->post('/api/v1/logout', [App\Controllers\Api\AuthController::class, 'logout']);
$router->get('/api/v1/me', [App\Controllers\Api\AuthController::class, 'me']);
$router->get('/api/v1/dashboard', [App\Controllers\Api\DashboardController::class, 'summary']);
$router->get('/api/v1/leads', [App\Controllers\Api\LeadController::class, 'index']);
$router->get('/api/v1/leads/opcoes', [App\Controllers\Api\LeadController::class, 'options']);
$router->get('/api/v1/leads/{id}', [App\Controllers\Api\LeadController::class, 'show']);
$router->post('/api/v1/leads/{id}/status', [App\Controllers\Api\LeadController::class, 'updateStatus']);
$router->post('/api/v1/leads/{id}/atribuir', [App\Controllers\Api\LeadController::class, 'assign']);
$router->post('/api/v1/leads/{id}/notas', [App\Controllers\Api\LeadController::class, 'storeNote']);
$router->get('/api/v1/aprovacoes', [App\Controllers\Api\ApprovalController::class, 'index']);
$router->post('/api/v1/aprovacoes/{id}/decidir', [App\Controllers\Api\ApprovalController::class, 'decide']);
$router->get('/api/v1/pedidos', [App\Controllers\Api\OrderController::class, 'index']);
$router->get('/api/v1/pedidos/aprovar-documentos', [App\Controllers\Api\OrderController::class, 'pendingDocuments']);
$router->post('/api/v1/pedidos/{id}/aprovar-documentos', [App\Controllers\Api\OrderController::class, 'approveDocuments']);
$router->get('/api/v1/pedidos/{id}', [App\Controllers\Api\OrderController::class, 'show']);
$router->post('/api/v1/pedidos/{id}/status', [App\Controllers\Api\OrderController::class, 'markStatus']);
$router->post('/api/v1/pedidos/{id}/rastreio', [App\Controllers\Api\OrderController::class, 'updateTracking']);
$router->get('/api/v1/comissoes', [App\Controllers\Api\CommissionController::class, 'index']);
$router->post('/api/v1/comissoes/{id}/pagar', [App\Controllers\Api\CommissionController::class, 'markPaid']);
$router->get('/api/v1/clientes', [App\Controllers\Api\ClientController::class, 'index']);
$router->get('/api/v1/clientes/{id}', [App\Controllers\Api\ClientController::class, 'show']);
$router->get('/api/v1/orcamentos', [App\Controllers\Api\QuoteController::class, 'index']);
$router->get('/api/v1/orcamentos/{id}', [App\Controllers\Api\QuoteController::class, 'show']);
$router->get('/api/v1/materiais', [App\Controllers\Api\MaterialController::class, 'index']);
$router->get('/api/v1/entregas', [App\Controllers\Api\OrderController::class, 'deliveries']);
$router->get('/api/v1/garantias', [App\Controllers\Api\WarrantyController::class, 'index']);
$router->get('/api/v1/garantias/{id}', [App\Controllers\Api\WarrantyController::class, 'show']);
$router->post('/api/v1/garantias/{id}/status', [App\Controllers\Api\WarrantyController::class, 'updateStatus']);
$router->get('/api/v1/rede', [App\Controllers\Api\UserController::class, 'index']);
$router->get('/api/v1/rede/opcoes', [App\Controllers\Api\UserController::class, 'options']);
$router->post('/api/v1/rede', [App\Controllers\Api\UserController::class, 'store']);
$router->get('/api/v1/rede/{id}', [App\Controllers\Api\UserController::class, 'show']);
$router->post('/api/v1/rede/{id}', [App\Controllers\Api\UserController::class, 'update']);
$router->post('/api/v1/device-token', [App\Controllers\Api\DeviceTokenController::class, 'register']);
$router->get('/api/v1/desempenho/equipe', [App\Controllers\Api\PerformanceController::class, 'team']);
$router->get('/api/v1/financeiro/contas', [App\Controllers\Api\FinanceController::class, 'accounts']);
$router->get('/api/v1/financeiro/pagar', [App\Controllers\Api\FinanceController::class, 'payable']);
$router->get('/api/v1/financeiro/receber', [App\Controllers\Api\FinanceController::class, 'receivable']);
$router->post('/api/v1/financeiro/lancamentos/{id}/pagar', [App\Controllers\Api\FinanceController::class, 'markPaid']);

// Fase 85: paridade do app com as fases 79-84 do painel web.
$router->get('/api/v1/treinamento', [App\Controllers\Api\TrainingController::class, 'index']);
$router->post('/api/v1/treinamento/progresso', [App\Controllers\Api\TrainingController::class, 'reportProgress']);
$router->get('/api/v1/metas', [App\Controllers\Api\GoalController::class, 'index']);
$router->get('/api/v1/metas/{id}/ritmo', [App\Controllers\Api\GoalController::class, 'pace']);
$router->get('/api/v1/cotacoes-maquina', [App\Controllers\Api\MachineQuoteController::class, 'index']);
$router->get('/api/v1/cotacoes-maquina/{id}', [App\Controllers\Api\MachineQuoteController::class, 'show']);
$router->post('/api/v1/cotacoes-maquina/{id}/responder', [App\Controllers\Api\MachineQuoteController::class, 'respond']);
$router->get('/api/v1/emails-profissionais', [App\Controllers\Api\LicenciadoEmailController::class, 'index']);
$router->post('/api/v1/emails-profissionais', [App\Controllers\Api\LicenciadoEmailController::class, 'store']);
$router->get('/api/v1/proposta-facil/opcoes', [App\Controllers\Api\PropostaController::class, 'options']);
$router->post('/api/v1/proposta-facil', [App\Controllers\Api\PropostaController::class, 'store']);
$router->post('/api/v1/proposta-facil/{id}/concluir', [App\Controllers\Api\PropostaController::class, 'conclude']);

// Fase 103: paridade do app com as 7 ferramentas premium (Fases 92-97) + Meu Link de Vendas (Fase 99, gratuito).
$router->get('/api/v1/meu-link', [App\Controllers\Api\SellerLinkController::class, 'show']);
$router->get('/api/v1/simulador-comissao', [App\Controllers\Api\CommissionSimulatorController::class, 'index']);
$router->get('/api/v1/indicacoes', [App\Controllers\Api\ReferralController::class, 'index']);
$router->post('/api/v1/indicacoes', [App\Controllers\Api\ReferralController::class, 'store']);
$router->post('/api/v1/indicacoes/{id}/vincular', [App\Controllers\Api\ReferralController::class, 'link']);
$router->post('/api/v1/indicacoes/{id}/contato', [App\Controllers\Api\ReferralController::class, 'markContacted']);
$router->post('/api/v1/indicacoes/{id}/descartar', [App\Controllers\Api\ReferralController::class, 'discard']);
$router->post('/api/v1/indicacoes/{id}/premio', [App\Controllers\Api\ReferralController::class, 'setReward']);
$router->post('/api/v1/indicacoes/{id}/premio-pago', [App\Controllers\Api\ReferralController::class, 'markRewardPaid']);
$router->get('/api/v1/certificacao', [App\Controllers\Api\CertificationController::class, 'show']);
$router->get('/api/v1/certificacao/pdf', [App\Controllers\Api\CertificationController::class, 'pdf']);
$router->get('/api/v1/mural', [App\Controllers\Api\MuralController::class, 'index']);
$router->get('/api/v1/calendario', [App\Controllers\Api\CalendarController::class, 'index']);
$router->post('/api/v1/calendario', [App\Controllers\Api\CalendarController::class, 'store']);
$router->get('/api/v1/calendario/{id}', [App\Controllers\Api\CalendarController::class, 'show']);
$router->post('/api/v1/calendario/{id}', [App\Controllers\Api\CalendarController::class, 'update']);
$router->post('/api/v1/calendario/{id}/status', [App\Controllers\Api\CalendarController::class, 'markStatus']);
$router->post('/api/v1/calendario/{id}/excluir', [App\Controllers\Api\CalendarController::class, 'destroy']);

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
$router->post('/painel/meus-pedidos/{id}/documentos', [App\Controllers\ClientPortalController::class, 'uploadOrderDocuments']);
$router->post('/painel/meus-pedidos/{id}/aceitar-termos', [App\Controllers\ClientPortalController::class, 'acceptTerms']);

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
$router->get('/painel/fabrica/rede', [App\Controllers\FactoryController::class, 'rede']);
$router->get('/painel/fabrica/pedidos', [App\Controllers\FactoryController::class, 'consultaPedidos']);
$router->get('/painel/fabrica/leads', [App\Controllers\FactoryController::class, 'leads']);
$router->post('/painel/fabrica/{id}/entrega', [App\Controllers\FactoryController::class, 'updateDelivery']);
$router->post('/painel/fabrica/{id}/entregue', [App\Controllers\FactoryController::class, 'markDelivered']);
$router->get('/painel/fabrica/{id}/termo-garantia', [App\Controllers\FactoryController::class, 'downloadWarrantyTerm']);
$router->get('/painel/fabrica/{id}/arquivo/{field}', [App\Controllers\FactoryController::class, 'downloadDocument']);
$router->get('/painel/fabrica/{id}/comprovante-pagamento', [App\Controllers\FactoryController::class, 'downloadFactoryPaymentProof']);

$router->get('/painel/influenciador', [App\Controllers\InfluencerController::class, 'dashboard']);
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
$router->get('/painel/usuarios/{id}/clientes', [App\Controllers\UserController::class, 'clientsForSeller']);
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
$router->post('/painel/licenciados/{id}/reenviar-assinatura', [App\Controllers\LicenciadoApprovalController::class, 'resendSignature']);
$router->post('/painel/envelopes/{envelopeId}/baixar-contrato', [App\Controllers\LicenciadoApprovalController::class, 'retryDownloadContract']);

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

$router->get('/painel/calculadora-economia-diesel', [App\Controllers\LeaseCalculatorController::class, 'index']);
$router->post('/painel/calculadora-economia-diesel', [App\Controllers\LeaseCalculatorController::class, 'calcular']);
$router->post('/painel/calculadora-economia-diesel/pdf', [App\Controllers\LeaseCalculatorController::class, 'downloadPdf']);
$router->get('/painel/calculadora-locacao', [App\Controllers\LeaseCalculatorController::class, 'redirectLegacySlug']);

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
$router->get('/painel/clientes/{id}/excluir-preview', [App\Controllers\ClientController::class, 'confirmDestroy']);
$router->post('/painel/clientes/{id}/excluir-confirmado', [App\Controllers\ClientController::class, 'destroyConfirmed']);
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
$router->post('/painel/configuracoes/whatsapp/bot', [App\Controllers\WhatsAppSettingsController::class, 'toggleBot']);
$router->post('/painel/configuracoes/whatsapp/evento/{eventKey}', [App\Controllers\WhatsAppSettingsController::class, 'updateTemplate']);

$router->get('/painel/email', [App\Controllers\EmailInboxController::class, 'index']);
$router->get('/painel/email/{uid}', [App\Controllers\EmailInboxController::class, 'show']);
$router->post('/painel/email/{uid}/responder', [App\Controllers\EmailInboxController::class, 'reply']);
$router->get('/painel/email/{uid}/anexo/{partNum}', [App\Controllers\EmailInboxController::class, 'downloadAttachment']);

$router->get('/painel/configuracoes/empresa', [App\Controllers\CompanySettingsController::class, 'index']);
$router->post('/painel/configuracoes/empresa', [App\Controllers\CompanySettingsController::class, 'update']);
$router->post('/painel/configuracoes/empresa/termos', [App\Controllers\CompanySettingsController::class, 'updateTerms']);
$router->post('/painel/configuracoes/empresa/cota-emails', [App\Controllers\CompanySettingsController::class, 'updateEmailQuota']);

$router->get('/painel/configuracoes/tutoriais', [App\Controllers\TutorialController::class, 'manage']);
$router->post('/painel/configuracoes/tutoriais', [App\Controllers\TutorialController::class, 'store']);
$router->post('/painel/configuracoes/tutoriais/{id}/excluir', [App\Controllers\TutorialController::class, 'destroy']);
$router->get('/painel/tutoriais', [App\Controllers\TutorialController::class, 'client']);

$router->get('/painel/mural', [App\Controllers\MuralController::class, 'index']);
$router->get('/painel/mural/feed', [App\Controllers\MuralController::class, 'feed']);
$router->get('/painel/certificacao', [App\Controllers\CertificationController::class, 'index']);
$router->get('/painel/certificacao/pdf', [App\Controllers\CertificationController::class, 'pdf']);
$router->get('/painel/treinamento', [App\Controllers\SellerTrainingController::class, 'show']);
$router->post('/painel/treinamento/progresso', [App\Controllers\SellerTrainingController::class, 'reportProgress']);

$router->get('/painel/dados-bancarios', [App\Controllers\BankAccountController::class, 'show']);
$router->post('/painel/dados-bancarios', [App\Controllers\BankAccountController::class, 'update']);
$router->get('/painel/configuracoes/treinamento', [App\Controllers\SellerTrainingController::class, 'manage']);
$router->post('/painel/configuracoes/treinamento', [App\Controllers\SellerTrainingController::class, 'storeVideo']);
$router->post('/painel/configuracoes/treinamento/{id}/excluir', [App\Controllers\SellerTrainingController::class, 'destroyVideo']);
$router->post('/painel/configuracoes/treinamento/{id}/{direction}', [App\Controllers\SellerTrainingController::class, 'moveVideo']);
$router->post('/painel/configuracoes/treinamento/modulos', [App\Controllers\SellerTrainingController::class, 'storeModule']);
$router->post('/painel/configuracoes/treinamento/modulos/{id}/excluir', [App\Controllers\SellerTrainingController::class, 'destroyModule']);
$router->post('/painel/configuracoes/treinamento/modulos/{id}/{direction}', [App\Controllers\SellerTrainingController::class, 'moveModule']);

// Contrato do Vendedor (Fase 105)
$router->get('/painel/contrato-vendedor', [App\Controllers\VendorContractController::class, 'show']);
$router->get('/painel/contrato-vendedor/baixar', [App\Controllers\VendorContractController::class, 'download']);
$router->post('/painel/contrato-vendedor/enviar', [App\Controllers\VendorContractController::class, 'upload']);
$router->get('/painel/contrato-vendedor/aprovar', [App\Controllers\VendorContractController::class, 'pendingApprovals']);
$router->post('/painel/contrato-vendedor/{id}/aprovar', [App\Controllers\VendorContractController::class, 'approve']);
$router->post('/painel/contrato-vendedor/{id}/reprovar', [App\Controllers\VendorContractController::class, 'reject']);
$router->get('/painel/contrato-vendedor/{id}/arquivo', [App\Controllers\VendorContractController::class, 'downloadSigned']);

// Pedidos
$router->get('/painel/pedidos', [App\Controllers\OrderController::class, 'index']);
$router->get('/painel/pedidos/novo', [App\Controllers\OrderController::class, 'create']);
$router->post('/painel/pedidos', [App\Controllers\OrderController::class, 'store']);
$router->get('/painel/pedidos/exportar', [App\Controllers\OrderController::class, 'export']);
$router->get('/painel/pedidos/aprovar-documentos', [App\Controllers\OrderController::class, 'pendingDocuments']);
$router->get('/painel/pedidos/{id}', [App\Controllers\OrderController::class, 'show']);
$router->get('/painel/pedidos/{id}/editar', [App\Controllers\OrderController::class, 'edit']);
$router->post('/painel/pedidos/{id}', [App\Controllers\OrderController::class, 'update']);
$router->post('/painel/pedidos/{id}/status', [App\Controllers\OrderController::class, 'markStatus']);
$router->post('/painel/pedidos/{id}/reembolsar', [App\Controllers\OrderController::class, 'refundPayment']);
$router->post('/painel/pedidos/{id}/excluir', [App\Controllers\OrderController::class, 'destroy']);
$router->post('/painel/pedidos/{id}/rastreio', [App\Controllers\OrderController::class, 'updateTracking']);
$router->post('/painel/pedidos/{id}/aprovar-documentos', [App\Controllers\OrderController::class, 'approveDocuments']);
$router->post('/painel/pedidos/{id}/faturamento-custo', [App\Controllers\OrderController::class, 'updateCostPriceBilling']);
$router->post('/painel/pedidos/{id}/comprovante-fabrica', [App\Controllers\OrderController::class, 'attachFactoryPaymentProof']);
$router->get('/painel/pedidos/{id}/comprovante-fabrica', [App\Controllers\OrderController::class, 'downloadFactoryPaymentProof']);
$router->get('/painel/pedidos/{id}/documento-veiculo', [App\Controllers\OrderController::class, 'downloadVehicleDocument']);
$router->get('/painel/pedidos/{id}/cnh', [App\Controllers\OrderController::class, 'downloadCnhDocument']);
$router->get('/painel/pedidos/{id}/arquivo/{field}', [App\Controllers\OrderController::class, 'downloadOrderFile']);
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
$router->post('/painel/orcamentos/{id}/excluir', [App\Controllers\QuoteController::class, 'destroy']);
$router->post('/painel/orcamentos/{id}/cobranca', [App\Controllers\PaymentController::class, 'generateForQuote']);

// Proposta Facil
$router->get('/painel/proposta-facil', [App\Controllers\PropostaController::class, 'create']);
$router->post('/painel/proposta-facil', [App\Controllers\PropostaController::class, 'store']);
$router->get('/painel/proposta-facil/resultado', [App\Controllers\PropostaController::class, 'show']);
$router->get('/painel/proposta-facil/pdf', [App\Controllers\PropostaController::class, 'pdf']);
$router->post('/painel/proposta-facil/concluir', [App\Controllers\PropostaController::class, 'conclude']);
$router->get('/painel/proposta-comercial', [App\Controllers\PropostaComercialController::class, 'create']);
$router->post('/painel/proposta-comercial/pdf', [App\Controllers\PropostaComercialController::class, 'pdf']);
$router->post('/painel/proposta-comercial/docx', [App\Controllers\PropostaComercialController::class, 'docx']);

$router->get('/painel/auditoria', [App\Controllers\AuditController::class, 'index']);

// Aprovacao de desconto
$router->get('/painel/liberacoes', [App\Controllers\ApprovalController::class, 'index']);
$router->post('/painel/aprovacoes/{id}/decidir', [App\Controllers\ApprovalController::class, 'decide']);

// Webhook Asaas (publico)
$router->post('/webhooks/asaas', [App\Controllers\PaymentController::class, 'webhook']);

// Webhook ClickSign (publico)
$router->post('/webhooks/clicksign', [App\Controllers\ClickSignWebhookController::class, 'clicksign']);

// Webhook Mercado Pago (publico) -- assinatura do Licenciado
$router->post('/webhooks/mercadopago', [App\Controllers\SubscriptionWebhookController::class, 'mercadopago']);
$router->get('/webhooks/mercadopago', [App\Controllers\SubscriptionWebhookController::class, 'mercadopago']);

// Assinatura do Licenciado (Fase 32)
$router->get('/painel/assinatura', [App\Controllers\SubscriptionController::class, 'index']);
$router->post('/painel/assinatura/comprar', [App\Controllers\SubscriptionController::class, 'purchase']);
$router->post('/painel/assinatura/vagas/comprar', [App\Controllers\SubscriptionController::class, 'purchaseSeats']);

$router->get('/painel/emails-profissionais', [App\Controllers\LicenciadoEmailController::class, 'index']);
$router->post('/painel/emails-profissionais', [App\Controllers\LicenciadoEmailController::class, 'store']);

// Calendario da equipe (Fase 92)
$router->get('/painel/calendario', [App\Controllers\CalendarController::class, 'index']);
$router->get('/painel/calendario/novo', [App\Controllers\CalendarController::class, 'create']);
$router->post('/painel/calendario', [App\Controllers\CalendarController::class, 'store']);
$router->get('/painel/calendario/{id}/editar', [App\Controllers\CalendarController::class, 'edit']);
$router->post('/painel/calendario/{id}/status', [App\Controllers\CalendarController::class, 'markStatus']);
$router->post('/painel/calendario/{id}/excluir', [App\Controllers\CalendarController::class, 'destroy']);
$router->post('/painel/calendario/{id}', [App\Controllers\CalendarController::class, 'update']);

// Link de vendas pessoal (Fase 93)
$router->get('/painel/meu-link', [App\Controllers\SellerLinkController::class, 'index']);
$router->get('/painel/indicacoes', [App\Controllers\ReferralController::class, 'index']);
$router->post('/painel/indicacoes', [App\Controllers\ReferralController::class, 'store']);
$router->post('/painel/indicacoes/{id}/vincular', [App\Controllers\ReferralController::class, 'link']);
$router->post('/painel/indicacoes/{id}/contato', [App\Controllers\ReferralController::class, 'markContacted']);
$router->post('/painel/indicacoes/{id}/descartar', [App\Controllers\ReferralController::class, 'discard']);
$router->post('/painel/indicacoes/{id}/premio', [App\Controllers\ReferralController::class, 'setReward']);
$router->post('/painel/indicacoes/{id}/premio-pago', [App\Controllers\ReferralController::class, 'markRewardPaid']);
$router->get('/v/{slug}', [App\Controllers\SellerLandingController::class, 'show']);
$router->post('/v/{slug}', [App\Controllers\SellerLandingController::class, 'submitLead']);

$router->get('/painel/simulador-comissao', [App\Controllers\CommissionSimulatorController::class, 'index']);
$router->get('/painel/emails-profissionais/admin', [App\Controllers\LicenciadoEmailController::class, 'adminIndex']);
$router->post('/painel/emails-profissionais/admin/{id}/aprovar', [App\Controllers\LicenciadoEmailController::class, 'approve']);
$router->post('/painel/emails-profissionais/admin/{id}/recusar', [App\Controllers\LicenciadoEmailController::class, 'reject']);

// Custos do Licenciado (Fase 32, sempre liberado)
$router->get('/painel/meus-custos', [App\Controllers\LicenciadoExpenseController::class, 'index']);
$router->post('/painel/meus-custos', [App\Controllers\LicenciadoExpenseController::class, 'store']);
$router->post('/painel/meus-custos/{id}/excluir', [App\Controllers\LicenciadoExpenseController::class, 'destroy']);

// Aceite de comissao (publico, Fase 32)
$router->get('/aceite-comissao/{token}', [App\Controllers\AcceptanceController::class, 'show']);
$router->post('/aceite-comissao/{token}/aceitar', [App\Controllers\AcceptanceController::class, 'accept']);

// Webhook Evolution API (publico) -- uma URL so pra todas as instancias por-usuario (Fase 33)
$router->post('/webhooks/evolution', [App\Controllers\WhatsAppWebhookController::class, 'receive']);

// Painel de WhatsApp do Licenciado/Gestor/Vendedor (Fase 33, dentro da assinatura)
$router->get('/painel/whatsapp', [App\Controllers\WhatsAppInstanceController::class, 'index']);
$router->get('/painel/whatsapp/status', [App\Controllers\WhatsAppInstanceController::class, 'status']);
$router->post('/painel/whatsapp/conectar', [App\Controllers\WhatsAppInstanceController::class, 'connect']);
$router->post('/painel/whatsapp/desconectar', [App\Controllers\WhatsAppInstanceController::class, 'disconnect']);
$router->get('/painel/whatsapp/conversas', [App\Controllers\WhatsAppInboxController::class, 'index']);
$router->get('/painel/whatsapp/conversas/{id}', [App\Controllers\WhatsAppInboxController::class, 'show']);
$router->get('/painel/whatsapp/conversas/{id}/poll', [App\Controllers\WhatsAppInboxController::class, 'poll']);
$router->get('/painel/whatsapp/conversas/{id}/midia/{messageId}', [App\Controllers\WhatsAppInboxController::class, 'media']);
$router->post('/painel/whatsapp/conversas/{id}/enviar', [App\Controllers\WhatsAppInboxController::class, 'send']);
$router->post('/painel/whatsapp/conversas/{id}/enviar-midia', [App\Controllers\WhatsAppInboxController::class, 'sendMedia']);
$router->post('/painel/whatsapp/conversas/{id}/lead', [App\Controllers\WhatsAppInboxController::class, 'linkLead']);
$router->post('/painel/whatsapp/conversas/{id}/lead-novo', [App\Controllers\WhatsAppInboxController::class, 'createLead']);
$router->post('/painel/whatsapp/conversas/{id}/mensagens/{messageId}/apagar', [App\Controllers\WhatsAppInboxController::class, 'deleteMessage']);
$router->post('/painel/whatsapp/conversas/{id}/tags', [App\Controllers\WhatsAppInboxController::class, 'assignTag']);
$router->post('/painel/whatsapp/conversas/{id}/tags/{tagId}/remover', [App\Controllers\WhatsAppInboxController::class, 'removeTag']);
$router->post('/painel/whatsapp/sincronizar', [App\Controllers\WhatsAppInboxController::class, 'sync']);
$router->post('/painel/whatsapp/tags', [App\Controllers\WhatsAppInboxController::class, 'storeTag']);

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
$router->get('/painel/metas/{id}/ritmo', [App\Controllers\GoalController::class, 'pace']);
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
$router->post('/painel/financeiro/categorias', [App\Controllers\FinanceController::class, 'storeCategory']);
$router->get('/painel/financeiro/contas/{id}/editar', [App\Controllers\FinanceController::class, 'editTransaction']);
$router->post('/painel/financeiro/contas/{id}/excluir', [App\Controllers\FinanceController::class, 'destroyTransaction']);
$router->post('/painel/financeiro/contas/{id}/baixar', [App\Controllers\FinanceController::class, 'markPaid']);
$router->post('/painel/financeiro/contas/{id}', [App\Controllers\FinanceController::class, 'updateTransaction']);
$router->get('/painel/financeiro/anexos/{id}', [App\Controllers\FinanceController::class, 'downloadAttachment']);
$router->get('/painel/financeiro/comissoes', [App\Controllers\FinanceController::class, 'commissions']);
$router->get('/painel/financeiro/comissoes/exportar', [App\Controllers\FinanceController::class, 'exportCommissions']);
$router->post('/painel/financeiro/comissoes/{id}/baixar', [App\Controllers\FinanceController::class, 'markCommissionPaid']);
$router->get('/painel/financeiro/comissoes/anexos/{id}', [App\Controllers\FinanceController::class, 'downloadCommissionAttachment']);

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
