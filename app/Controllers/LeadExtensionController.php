<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\FileUpload;
use App\Core\Roles;
use App\Core\View;
use App\Models\LeadExtensionRequest;
use App\Models\User;

/**
 * Historico/auditoria de justificativas de extensao de prazo de Lead (Fase 36) -- pedido
 * explicito do usuario: Licenciado/Supervisor/Gerente/Admin veem, cada um escopado a propria
 * rede (Vendedor nao acessa essa tela, ele so submete a justificativa direto no card do Kanban).
 */
class LeadExtensionController
{
    public function index(): void
    {
        Auth::requireRole(array_merge(Roles::MANAGEMENT, Roles::NATIONAL_SUPPORT));
        $user = Auth::user();

        View::render('painel/leads/extensions', [
            'user' => $user,
            'requests' => LeadExtensionRequest::all($this->scopeFor($user)),
        ]);
    }

    public function downloadAttachment(string $id): void
    {
        Auth::requireRole(array_merge(Roles::MANAGEMENT, Roles::NATIONAL_SUPPORT));
        $user = Auth::user();

        $requests = LeadExtensionRequest::all($this->scopeFor($user));
        $request = null;
        foreach ($requests as $r) {
            if ((int) $r['id'] === (int) $id) {
                $request = $r;
                break;
            }
        }

        if (!$request || !$request['attachment_path']) {
            http_response_code(404);
            exit('Anexo não encontrado.');
        }

        $path = FileUpload::path('lead_extensions', $request['attachment_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        header('Content-Type: ' . (mime_content_type($path) ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="extensao-lead-' . (int) $request['lead_id'] . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** null = sem escopo (Admin). Demais papeis: quem pediu a extensao (requested_by_user_id)
     *  precisa estar na rede de quem esta vendo -- mesmo padrao ja usado em toda parte do CRM. */
    private function scopeFor(array $user): ?array
    {
        if ($user['role_slug'] === 'admin') {
            return null;
        }
        if ($user['role_slug'] === 'supervisor') {
            return User::supervisedIds((int) $user['id']);
        }
        if ($user['role_slug'] === 'gerente') {
            return User::nationalIds((int) $user['id']);
        }

        return User::downlineIds((int) $user['id']);
    }
}
