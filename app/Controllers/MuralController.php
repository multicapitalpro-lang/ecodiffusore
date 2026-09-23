<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\GoalAchievementCheck;
use App\Core\SubscriptionGate;
use App\Core\View;
use App\Models\TeamFeedEntry;
use App\Models\User;

/** Fase 97: Mural de Conquistas -- setima e ultima das 7 ferramentas premium aprovadas. Feed
 *  compartilhado da rede do Licenciado, atualizado quase em tempo real via polling (setInterval +
 *  fetch na view, mesmo padrao ja usado no Inbox do WhatsApp -- sem websocket, sem cron, so' uma
 *  tabela lida a cada poll). */
class MuralController
{
    private const ELIGIBLE_ROLES = ['licenciado', 'gestor', 'vendedor'];

    private function licenciadoId(array $user): ?int
    {
        return $user['role_slug'] === 'licenciado' ? (int) $user['id'] : User::licenciadoIdFor((int) $user['id']);
    }

    public function index(): void
    {
        Auth::requireRole(self::ELIGIBLE_ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'mural_conquistas');

        // Sem cron nesse plano Hostinger -- roda aqui, na carga da pagina (o poll em si so' le a
        // tabela, nao recalcula nada pesado a cada 8s).
        GoalAchievementCheck::processDue();

        $licenciadoId = $this->licenciadoId($user);
        $entries = $licenciadoId ? TeamFeedEntry::forLicenciado($licenciadoId) : [];

        View::render('painel/mural/index', ['user' => $user, 'entries' => $entries]);
    }

    /** JSON pro polling da view -- so' leitura, nunca dispara os checks lazy (esses ja rodam em
     *  index()/GoalController::index()/ReferralController::index()). */
    public function feed(): void
    {
        Auth::requireRole(self::ELIGIBLE_ROLES);
        $user = Auth::user();
        header('Content-Type: application/json');

        if (!SubscriptionGate::hasAccess($user)) {
            echo json_encode(['entries' => []]);
            return;
        }

        $licenciadoId = $this->licenciadoId($user);
        $sinceId = (int) ($_GET['since'] ?? 0);
        $entries = $licenciadoId ? TeamFeedEntry::forLicenciado($licenciadoId, $sinceId) : [];

        echo json_encode(['entries' => array_map(fn ($e) => [
            'id' => (int) $e['id'],
            'type' => $e['type'],
            'message' => $e['message'],
            'created_at' => $e['created_at'],
        ], $entries)]);
    }
}
