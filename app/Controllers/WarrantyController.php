<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\FileUpload;
use App\Core\Pdf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\WarrantyRequest;

/** Pagina de controle de Garantias -- so Admin e Gerente Geral decidem (aprovar/reprovar), mesmo
 *  escopo de acesso da aprovacao de cadastro de Licenciado (Roles::SUPERVISOR_ASSIGNMENT). Nao e'
 *  uma decisao regional/de comissao como Pedidos -- e' garantia do produto, decisao nacional. */
class WarrantyController
{
    public function index(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $user = Auth::user();
        $status = $_GET['status'] ?? null;

        View::render('painel/warranties/index', [
            'user' => $user,
            'warranties' => WarrantyRequest::forScope($this->scopeSellerIds($user), $status ?: null),
            'status' => $status,
        ]);
    }

    public function show(string $id): void
    {
        $warranty = $this->authorizeWarranty((int) $id);
        View::render('painel/warranties/show', [
            'user' => Auth::user(),
            'warranty' => $warranty,
            'attachments' => WarrantyRequest::attachmentsFor((int) $warranty['id']),
        ]);
    }

    public function updateStatus(string $id): void
    {
        $warranty = $this->authorizeWarranty((int) $id);
        $id = (int) $id;
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/garantias/{$id}?erro=1");
        }

        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['em_analise', 'aprovada', 'rejeitada', 'concluida'], true)) {
            Router::redirect("/painel/garantias/{$id}?erro=1");
        }

        WarrantyRequest::updateStatus($id, $status, trim($_POST['resolution_note'] ?? ''), (int) $user['id']);
        AuditLog::record((int) $user['id'], 'garantia_status_alterado', 'warranty_request', $id, ['status' => $warranty['status']], ['status' => $status]);

        Router::redirect("/painel/garantias/{$id}?sucesso=1");
    }

    public function downloadAttachment(string $id, string $attachmentId): void
    {
        $warranty = $this->authorizeWarranty((int) $id);

        $attachment = WarrantyRequest::findAttachment((int) $attachmentId);
        if (!$attachment || (int) $attachment['warranty_request_id'] !== (int) $warranty['id']) {
            http_response_code(404);
            exit('Anexo não encontrado.');
        }

        $path = FileUpload::path('warranties', $attachment['stored_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        header('Content-Type: ' . (mime_content_type($path) ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($attachment['original_name'] ?: $attachment['stored_path']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Termo de Garantia em PDF -- so pode ser gerado depois de aprovada (prova formal pro
     *  comprador). Mesmo cliente tambem consegue baixar, ver ClientPortalController::downloadWarrantyTerm(). */
    public function downloadTerm(string $id): void
    {
        $warranty = $this->authorizeWarranty((int) $id);
        if ($warranty['status'] !== 'aprovada' && $warranty['status'] !== 'concluida') {
            Router::redirect("/painel/garantias/{$id}?erro=2");
        }

        ob_start();
        View::render('painel/warranties/term_pdf', [
            'warranty' => $warranty,
            'items' => OrderItem::forOrder((int) $warranty['order_id']),
        ], null);
        $html = ob_get_clean();

        Pdf::download($html, 'termo-garantia-pedido-' . (int) $warranty['order_id'] . '.pdf', 'portrait');
    }

    /** Mesmo padrao de OrderController::authorizeOrder -- barreira contra acesso direto por URL a
     *  garantia fora do escopo (rede) de quem esta logado. */
    private function authorizeWarranty(int $id): array
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $user = Auth::user();

        $warranty = WarrantyRequest::find($id);
        if (!$warranty) {
            Router::redirect('/painel/garantias');
        }

        if (!$this->canAccessSeller($user, (int) ($warranty['seller_id'] ?? 0))) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        return $warranty;
    }

    /** Admin ve tudo; Gerente ve so a rede nacional dele (mesmo escopo de
     *  LicenciadoApprovalController). Ninguem mais chega aqui (gate em Roles::SUPERVISOR_ASSIGNMENT). */
    private function scopeSellerIds(array $user): ?array
    {
        return $user['role_slug'] === 'admin' ? null : User::nationalIds((int) $user['id']);
    }

    private function canAccessSeller(array $user, int $sellerId): bool
    {
        if ($user['role_slug'] === 'admin') {
            return true;
        }
        return in_array($sellerId, User::nationalIds((int) $user['id']), true);
    }
}
