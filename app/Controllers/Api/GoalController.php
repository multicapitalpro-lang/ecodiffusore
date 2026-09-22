<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\Goal;

/** Metas pro app (Fase 85) -- mesma leitura de App\Controllers\GoalController::index() (metas
 *  que sao PRA quem esta logado ou que ELE criou; admin ve tudo), sem tela de criacao (fica so no
 *  painel web por enquanto -- formulario com "toda a equipe" e' mais confortavel em tela grande). */
class GoalController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Metas.', 403);
        }

        $goals = Goal::all();
        $today = date('Y-m-d');

        if ($user['role_slug'] !== 'admin') {
            $goals = array_values(array_filter($goals, fn ($g) =>
                (int) ($g['seller_id'] ?? 0) === (int) $user['id']
                || (int) ($g['created_by'] ?? 0) === (int) $user['id']
            ));
        }

        $map = function (array $g): array {
            $p = Goal::progress($g);
            return [
                'id' => (int) $g['id'],
                'name' => $g['name'],
                'seller_name' => $g['seller_name'],
                'start_date' => $g['start_date'],
                'end_date' => $g['end_date'],
                'metric_type' => $g['metric_type'] ?? 'valor',
                'achieved' => $p['achieved'],
                'target' => $p['target'],
                'pct' => $p['pct'],
                'reached' => $p['reached'],
                'reward_description' => $g['reward_description'],
                'reward_amount' => $g['reward_amount'] !== null ? (float) $g['reward_amount'] : null,
                'reward_paid' => !empty($g['reward_paid']),
            ];
        };

        ApiResponse::json([
            'active' => array_map($map, array_values(array_filter($goals, fn ($g) => $g['end_date'] >= $today))),
            'inactive' => array_map($map, array_values(array_filter($goals, fn ($g) => $g['end_date'] < $today))),
        ]);
    }
}
