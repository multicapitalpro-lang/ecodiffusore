<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\MachineQuoteRequest;
use App\Models\User;

/** Cotacoes de maquina agricola pro app (Fase 85) -- mesmo escopo/regra de
 *  App\Controllers\MachineQuoteController. Sem as fotos aqui (ficam so no painel web por
 *  enquanto -- endpoint de foto exige auth por sessao, nao por token, ver
 *  MachineQuoteController::downloadPhoto()); o app mostra so os dados de texto da solicitacao. */
class MachineQuoteController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Cotacoes de Maquina.', 403);
        }

        $status = $_GET['status'] ?? null;
        $quotes = MachineQuoteRequest::forScope($this->scopeUserIds($user), $status ?: null, $this->includesUnassigned($user['role_slug']));

        ApiResponse::json([
            'quotes' => array_map(fn ($q) => $this->publicQuote($q), $quotes),
            'can_respond' => in_array($user['role_slug'], ['admin', 'gerente'], true),
        ]);
    }

    public function show(string $id): void
    {
        $quote = $this->authorizeQuote((int) $id);
        $user = ApiAuth::requireUser();

        ApiResponse::json([
            'quote' => $this->publicQuote($quote),
            'can_respond' => in_array($user['role_slug'], ['admin', 'gerente'], true),
        ]);
    }

    public function respond(string $id): void
    {
        $quote = $this->authorizeQuote((int) $id);
        $id = (int) $id;
        $user = ApiAuth::requireUser();

        if (!in_array($user['role_slug'], ['admin', 'gerente'], true)) {
            ApiResponse::error('So Gerente e Admin podem inserir o valor.', 403);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $price = $body['quoted_price'] ?? null;
        if (!is_numeric($price) || (float) $price <= 0) {
            ApiResponse::error('Informe um valor valido.', 422);
        }

        MachineQuoteRequest::markResponded($id, (int) $user['id'], (float) $price, trim($body['internal_notes'] ?? ''));

        ApiResponse::json(['quote' => $this->publicQuote(MachineQuoteRequest::find($id))]);
    }

    private function publicQuote(array $q): array
    {
        return [
            'id' => (int) $q['id'],
            'client_name' => $q['client_name'],
            'client_whatsapp' => $q['client_whatsapp'],
            'client_city' => $q['client_city'],
            'machine_type' => $q['machine_type'],
            'brand' => $q['brand'],
            'model' => $q['model'],
            'power' => $q['power'],
            'hose_measure' => $q['hose_measure'],
            'status' => $q['status'],
            'assignee_name' => $q['assignee_name'] ?? null,
            'quoted_price' => $q['quoted_price'] !== null ? (float) $q['quoted_price'] : null,
            'internal_notes' => $q['internal_notes'],
            'responder_name' => $q['responder_name'] ?? null,
            'responded_at' => $q['responded_at'],
            'created_at' => $q['created_at'],
        ];
    }

    private function authorizeQuote(int $id): array
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Cotacoes de Maquina.', 403);
        }

        $quote = MachineQuoteRequest::find($id);
        if (!$quote) {
            ApiResponse::error('Cotacao nao encontrada.', 404);
        }

        $scope = $this->scopeUserIds($user);
        $assignedId = (int) ($quote['assigned_user_id'] ?? 0);
        $allowed = $scope === null
            || in_array($assignedId, $scope, true)
            || ($assignedId === 0 && $this->includesUnassigned($user['role_slug']));
        if (!$allowed) {
            ApiResponse::error('Sem permissao pra ver esta cotacao.', 403);
        }

        return $quote;
    }

    private function scopeUserIds(array $user): ?array
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

    private function includesUnassigned(string $role): bool
    {
        return in_array($role, ['gerente', 'supervisor', Roles::REGIONAL_OWNER], true);
    }
}
