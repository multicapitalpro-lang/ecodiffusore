<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WarrantyRequest;

/** Fase 76h: Pos-venda de Instalacao (Garantias) pro app -- mesmo escopo estrito
 *  (Roles::SUPERVISOR_ASSIGNMENT = admin/gerente) de App\Controllers\WarrantyController. */
class WarrantyController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::SUPERVISOR_ASSIGNMENT, true)) {
            ApiResponse::error('Sem permissao pra ver Pos-venda de Instalacao.', 403);
        }

        $status = $_GET['status'] ?? null;
        $warranties = WarrantyRequest::forScope($this->scopeSellerIds($user), $status ?: null);

        ApiResponse::json(['warranties' => array_map(fn ($w) => [
            'id' => (int) $w['id'],
            'order_id' => (int) $w['order_id'],
            'client_name' => $w['client_name'],
            'seller_name' => $w['seller_name'],
            'status' => $w['status'],
            'created_at' => $w['created_at'],
        ], $warranties)]);
    }

    public function show(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::SUPERVISOR_ASSIGNMENT, true)) {
            ApiResponse::error('Sem permissao pra ver Pos-venda de Instalacao.', 403);
        }

        $id = (int) $id;
        $warranty = WarrantyRequest::find($id);
        if (!$warranty) {
            ApiResponse::error('Registro nao encontrado.', 404);
        }
        if (!$this->canAccessSeller($user, (int) ($warranty['seller_id'] ?? 0))) {
            ApiResponse::error('Sem permissao pra ver este registro.', 403);
        }

        ApiResponse::json(['warranty' => [
            'id' => (int) $warranty['id'],
            'order_id' => (int) $warranty['order_id'],
            'client_name' => $warranty['client_name'],
            'client_city' => $warranty['client_city'],
            'client_state' => $warranty['client_state'],
            'status' => $warranty['status'],
            'resolution_note' => $warranty['resolution_note'] ?? null,
            'resolved_by_name' => $warranty['resolved_by_name'] ?? null,
            'created_at' => $warranty['created_at'],
        ]]);
    }

    public function updateStatus(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::SUPERVISOR_ASSIGNMENT, true)) {
            ApiResponse::error('Sem permissao pra decidir Pos-venda de Instalacao.', 403);
        }

        $id = (int) $id;
        $warranty = WarrantyRequest::find($id);
        if (!$warranty) {
            ApiResponse::error('Registro nao encontrado.', 404);
        }
        if (!$this->canAccessSeller($user, (int) ($warranty['seller_id'] ?? 0))) {
            ApiResponse::error('Sem permissao pra decidir este registro.', 403);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $status = $body['status'] ?? '';
        if (!in_array($status, ['em_analise', 'aprovada', 'rejeitada', 'concluida'], true)) {
            ApiResponse::error('Status invalido.', 422);
        }

        WarrantyRequest::updateStatus($id, $status, trim($body['resolution_note'] ?? ''), (int) $user['id']);
        AuditLog::record((int) $user['id'], 'garantia_status_alterado', 'warranty_request', $id, ['status' => $warranty['status']], ['status' => $status]);

        ApiResponse::json(['ok' => true]);
    }

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
