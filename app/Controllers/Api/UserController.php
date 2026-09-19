<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\User;

/** Fase 76h: Minha Rede pro app -- leitura da mesma lista de App\Controllers\UserController::index()
 *  (escopo por hierarquia), devolvendo JSON. So leitura por enquanto -- cadastrar/editar usuario
 *  fica pra uma fase futura (formulario mais pesado, menor prioridade que status/consulta). */
class UserController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::USER_MANAGEMENT, true)) {
            ApiResponse::error('Sem permissao pra ver Minha Rede.', 403);
        }

        if ($user['role_slug'] === 'admin') {
            $scoped = User::all();
        } else {
            $downline = User::downlineIds((int) $user['id']);
            $scoped = array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $downline, true)));
        }

        ApiResponse::json(['users' => array_map(fn ($u) => [
            'id' => (int) $u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
            'role_slug' => $u['role_slug'],
            'role_name' => $u['role_name'],
            'status' => $u['status'],
            'city' => $u['city'] ?? null,
            'state' => $u['state'] ?? null,
            'licenciado_code' => $u['licenciado_code'] ?? null,
            'commission_pct' => $u['commission_pct'] !== null ? (float) $u['commission_pct'] : null,
        ], $scoped)]);
    }
}
