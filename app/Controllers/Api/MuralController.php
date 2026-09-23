<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\GoalAchievementCheck;
use App\Core\SubscriptionGate;
use App\Models\TeamFeedEntry;
use App\Models\User;

/** Fase 103: Mural de Conquistas pro app -- mesma logica de App\Controllers\MuralController,
 *  atras do mesmo paywall ('mural_conquistas'). No app, o refresh e' por pull-to-refresh/focus em
 *  vez do polling de 8s do painel web (mais adequado a app mobile) -- o endpoint aceita 'since'
 *  do mesmo jeito, pra permitir carregar so' o que e' novo se o app quiser. */
class MuralController
{
    private const ELIGIBLE_ROLES = ['licenciado', 'gestor', 'vendedor'];

    private function licenciadoId(array $user): ?int
    {
        return $user['role_slug'] === 'licenciado' ? (int) $user['id'] : User::licenciadoIdFor((int) $user['id']);
    }

    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], self::ELIGIBLE_ROLES, true)) {
            ApiResponse::error('Papel sem acesso a essa função.', 403);
        }
        if (!SubscriptionGate::hasAccess($user)) {
            ApiResponse::error('Assinatura do Licenciado inativa -- regularize no painel web.', 402);
        }

        GoalAchievementCheck::processDue();

        $licenciadoId = $this->licenciadoId($user);
        $sinceId = (int) ($_GET['since'] ?? 0);
        $entries = $licenciadoId ? TeamFeedEntry::forLicenciado($licenciadoId, $sinceId) : [];

        ApiResponse::json(['entries' => array_map(fn ($e) => [
            'id' => (int) $e['id'],
            'type' => $e['type'],
            'message' => $e['message'],
            'created_at' => $e['created_at'],
        ], $entries)]);
    }
}
