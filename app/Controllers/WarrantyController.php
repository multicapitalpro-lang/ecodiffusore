<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\FileUpload;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WarrantyRequest;

class WarrantyController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
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
            'isViewOnly' => in_array(Auth::user()['role_slug'], Roles::NATIONAL_SUPPORT, true),
        ]);
    }

    public function updateStatus(string $id): void
    {
        $warranty = $this->authorizeWarranty((int) $id);
        $id = (int) $id;
        $user = Auth::user();

        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

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

    /** Mesmo padrao de OrderController::authorizeOrder -- barreira contra acesso direto por URL a
     *  garantia fora do escopo (rede) de quem esta logado. */
    private function authorizeWarranty(int $id): array
    {
        Auth::requireRole(Roles::STAFF);
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

    /** Identico a OrderController::scopeFilters, mas sempre devolve uma lista de ids (nunca um
     *  seller_id unico) porque WarrantyRequest::forScope precisa de um array pro IN(). */
    private function scopeSellerIds(array $user): ?array
    {
        if ($user['role_slug'] === 'admin') {
            return null;
        }
        if ($user['role_slug'] === Roles::SELLER) {
            return [(int) $user['id']];
        }
        if ($user['role_slug'] === 'supervisor') {
            return User::supervisedIds((int) $user['id']);
        }
        if ($user['role_slug'] === 'gerente') {
            return User::nationalIds((int) $user['id']);
        }

        return User::downlineIds((int) $user['id']);
    }

    /** Identico a OrderController::canAccessSeller. */
    private function canAccessSeller(array $user, int $sellerId): bool
    {
        if ($user['role_slug'] === 'admin') {
            return true;
        }
        if ($user['role_slug'] === Roles::SELLER) {
            return $sellerId === (int) $user['id'];
        }
        if ($user['role_slug'] === 'supervisor') {
            return in_array($sellerId, User::supervisedIds((int) $user['id']), true);
        }
        if ($user['role_slug'] === 'gerente') {
            return in_array($sellerId, User::nationalIds((int) $user['id']), true);
        }

        return in_array($sellerId, User::downlineIds((int) $user['id']), true);
    }
}
