<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\FileUpload;
use App\Core\Notifier;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Core\WordDoc;
use App\Models\User;

/** Fase 105: contrato do Vendedor assinado fora do ClickSign (via gov.br, pra nao gerar custo por
 *  envelope) -- baixa o modelo personalizado, assina, envia de volta, e o Licenciado da rede dele
 *  aprova ou reprova. O bloqueio de acesso de verdade fica em Auth::requireRole() (Fase 105); esta
 *  tela do Vendedor em si so' usa Auth::requireLogin(), fica sempre acessivel enquanto ele estiver
 *  preso no gate -- mesmo padrao ja usado em SellerTrainingController/LicenciadoOnboardingController. */
class VendorContractController
{
    public function show(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if ($user['role_slug'] !== Roles::SELLER) {
            Router::redirect('/painel');
        }

        View::render('painel/vendor_contract/show', [
            'user' => $user,
            'licenciado' => User::licenciadoFor((int) $user['id']),
        ], null);
    }

    public function download(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if ($user['role_slug'] !== Roles::SELLER) {
            Router::redirect('/painel');
        }

        $licenciado = User::licenciadoFor((int) $user['id']);
        $commissionLabel = $user['commission_pct'] !== null
            ? number_format((float) $user['commission_pct'], 2, ',', '.') . '%'
            : 'conforme tabela de comissão vigente';

        WordDoc::downloadVendorContract([
            'vendorName' => $user['name'],
            'vendorEmail' => $user['email'],
            'licenciadoName' => $licenciado['name'] ?? null,
            'commissionLabel' => $commissionLabel,
        ]);
    }

    public function upload(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if ($user['role_slug'] !== Roles::SELLER) {
            Router::redirect('/painel');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/contrato-vendedor?erro=csrf');
        }

        try {
            $file = FileUpload::storeVendorContract($_FILES['contract'] ?? []);
        } catch (\RuntimeException $e) {
            Router::redirect('/painel/contrato-vendedor?erro=arquivo');
        }

        if (!$file) {
            Router::redirect('/painel/contrato-vendedor?erro=arquivo');
        }

        User::submitVendorContract((int) $user['id'], $file);
        Notifier::vendedorContratoEnviado($user);

        Router::redirect('/painel/contrato-vendedor?sucesso=1');
    }

    /** Licenciado (dono da rede) ou Admin (suporte) -- lista de Vendedores com contrato aguardando
     *  aprovacao. Mesmo padrao de LicenciadoOnboardingController::pendingApprovals()/
     *  OrderController::pendingDocuments(). */
    public function pendingApprovals(): void
    {
        Auth::requireRole(['licenciado', 'admin']);
        $user = Auth::user();

        View::render('painel/vendor_contract/pending', [
            'user' => $user,
            'vendedores' => User::pendingVendorContracts($user),
        ]);
    }

    public function approve(string $id): void
    {
        Auth::requireRole(['licenciado', 'admin']);
        $user = Auth::user();
        $id = (int) $id;

        $vendedor = $this->authorizeReview($user, $id);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/contrato-vendedor/aprovar?erro=csrf');
        }

        User::approveVendorContract($id, (int) $user['id']);
        Notifier::vendedorContratoAprovado($vendedor);

        Router::redirect('/painel/contrato-vendedor/aprovar?sucesso=1');
    }

    public function reject(string $id): void
    {
        Auth::requireRole(['licenciado', 'admin']);
        $user = Auth::user();
        $id = (int) $id;

        $vendedor = $this->authorizeReview($user, $id);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/contrato-vendedor/aprovar?erro=csrf');
        }

        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            Router::redirect('/painel/contrato-vendedor/aprovar?erro=motivo');
        }

        User::rejectVendorContract($id, (int) $user['id'], $reason);
        Notifier::vendedorContratoReprovado($vendedor, $reason);

        Router::redirect('/painel/contrato-vendedor/aprovar?sucesso=1');
    }

    /** Streaming autenticado do contrato assinado enviado pelo Vendedor -- o proprio Vendedor
     *  (revisar o que mandou), o Licenciado da rede dele, ou Admin. Nunca serve arquivo estatico. */
    public function downloadSigned(string $id): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $id = (int) $id;

        $vendedor = User::find($id);
        if (!$vendedor || empty($vendedor['vendedor_contract_path'])) {
            http_response_code(404);
            exit('Contrato não encontrado.');
        }

        $isSelf = (int) $user['id'] === $id;
        $isLicenciado = $user['role_slug'] === 'licenciado' && (User::licenciadoFor($id)['id'] ?? null) === (int) $user['id'];
        $isAdmin = $user['role_slug'] === 'admin';
        if (!$isSelf && !$isLicenciado && !$isAdmin) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        $path = FileUpload::path('vendor_contracts', $vendedor['vendedor_contract_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        header('Content-Type: ' . (mime_content_type($path) ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($vendedor['vendedor_contract_original_name'] ?: $vendedor['vendedor_contract_path']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function authorizeReview(array $user, int $vendedorId): array
    {
        $vendedor = User::find($vendedorId);
        if (!$vendedor || $vendedor['role_slug'] !== Roles::SELLER || $vendedor['vendedor_contract_status'] !== 'aguardando_aprovacao') {
            Router::redirect('/painel/contrato-vendedor/aprovar?erro=1');
        }

        if ($user['role_slug'] === 'licenciado') {
            $licenciado = User::licenciadoFor($vendedorId);
            if (!$licenciado || (int) $licenciado['id'] !== (int) $user['id']) {
                http_response_code(403);
                require BASE_PATH . '/app/Views/errors/403.php';
                exit;
            }
        }

        return $vendedor;
    }
}
