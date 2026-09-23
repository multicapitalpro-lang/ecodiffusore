<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\ReferralActivationCheck;
use App\Core\SubscriptionGate;
use App\Models\Referral;
use App\Models\User;

/** Fase 103: Indicacoes Premiadas pro app -- mesma logica de App\Controllers\ReferralController,
 *  atras do mesmo paywall ('indicacao_premiada'). O cadastro do indicado continua so' pelo painel
 *  web (tela de Usuarios) -- aqui e' so' registrar/gerenciar a indicacao em si. */
class ReferralController
{
    private const ELIGIBLE_ROLES = ['licenciado', 'gestor', 'vendedor'];

    private function requireGate(): array
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], self::ELIGIBLE_ROLES, true)) {
            ApiResponse::error('Papel sem acesso a essa função.', 403);
        }
        if (!SubscriptionGate::hasAccess($user)) {
            ApiResponse::error('Assinatura do Licenciado inativa -- regularize no painel web.', 402);
        }
        return $user;
    }

    private function authorize(array $referral, array $user): bool
    {
        if ($user['role_slug'] === 'admin' || (int) $referral['referrer_id'] === (int) $user['id']) {
            return true;
        }
        $licenciado = User::licenciadoFor((int) $referral['referrer_id']);
        return $licenciado !== null && (int) $licenciado['id'] === (int) $user['id'];
    }

    private function serialize(array $r, array $linkable): array
    {
        return [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'whatsapp' => $r['whatsapp'],
            'target_role' => $r['target_role'],
            'target_role_label' => Referral::TARGET_ROLE_LABELS[$r['target_role']] ?? $r['target_role'],
            'notes' => $r['notes'],
            'status' => $r['status'],
            'status_label' => Referral::STATUS_LABELS[$r['status']] ?? $r['status'],
            'converted_user_name' => $r['converted_user_name'] ?? null,
            'reward_description' => $r['reward_description'],
            'reward_amount' => $r['reward_amount'] !== null ? (float) $r['reward_amount'] : null,
            'reward_paid' => !empty($r['reward_paid']),
            'linkable_users' => array_map(fn ($u) => ['id' => (int) $u['id'], 'name' => $u['name']], $linkable),
        ];
    }

    public function index(): void
    {
        $user = $this->requireGate();
        ReferralActivationCheck::processDue();

        $referrals = Referral::forReferrer((int) $user['id']);
        $downlineIds = array_diff(User::downlineIds((int) $user['id']), [(int) $user['id']]);

        $out = array_map(function ($r) use ($downlineIds) {
            $linkable = in_array($r['status'], ['indicado', 'em_contato'], true) ? Referral::linkableUsers($r, $downlineIds) : [];
            return $this->serialize($r, $linkable);
        }, $referrals);

        ApiResponse::json(['referrals' => $out]);
    }

    public function store(): void
    {
        $user = $this->requireGate();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];

        if (trim($body['name'] ?? '') === '') {
            ApiResponse::error('Informe o nome da pessoa indicada.', 422);
        }

        $id = Referral::create([
            'referrer_id' => $user['id'],
            'name' => trim($body['name']),
            'whatsapp' => trim($body['whatsapp'] ?? ''),
            'target_role' => $body['target_role'] ?? 'vendedor',
            'notes' => trim($body['notes'] ?? ''),
        ]);

        ApiResponse::json(['ok' => true, 'id' => $id], 201);
    }

    public function link(string $id): void
    {
        $user = $this->requireGate();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];

        $referral = Referral::find((int) $id);
        if (!$referral || !$this->authorize($referral, $user)) {
            ApiResponse::error('Indicação não encontrada.', 404);
        }

        $targetUserId = (int) ($body['user_id'] ?? 0);
        $downlineIds = array_diff(User::downlineIds((int) $referral['referrer_id']), [(int) $referral['referrer_id']]);
        $candidates = array_column(Referral::linkableUsers($referral, $downlineIds), 'id');
        if (!$targetUserId || !in_array($targetUserId, $candidates, true)) {
            ApiResponse::error('Selecione um cadastro válido pra vincular.', 422);
        }

        Referral::linkToUser((int) $id, $targetUserId);
        ApiResponse::json(['ok' => true]);
    }

    public function markContacted(string $id): void
    {
        $user = $this->requireGate();
        $referral = Referral::find((int) $id);
        if (!$referral || !$this->authorize($referral, $user)) {
            ApiResponse::error('Indicação não encontrada.', 404);
        }
        Referral::markContacted((int) $id);
        ApiResponse::json(['ok' => true]);
    }

    public function discard(string $id): void
    {
        $user = $this->requireGate();
        $referral = Referral::find((int) $id);
        if (!$referral || !$this->authorize($referral, $user)) {
            ApiResponse::error('Indicação não encontrada.', 404);
        }
        Referral::markDiscarded((int) $id);
        ApiResponse::json(['ok' => true]);
    }

    public function setReward(string $id): void
    {
        $user = $this->requireGate();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];

        $referral = Referral::find((int) $id);
        if (!$referral || !$this->authorize($referral, $user)) {
            ApiResponse::error('Indicação não encontrada.', 404);
        }

        $amount = is_numeric($body['reward_amount'] ?? null) ? (float) $body['reward_amount'] : null;
        Referral::setReward((int) $id, trim($body['reward_description'] ?? ''), $amount);
        ApiResponse::json(['ok' => true]);
    }

    public function markRewardPaid(string $id): void
    {
        $user = $this->requireGate();
        $referral = Referral::find((int) $id);
        if (!$referral || !$this->authorize($referral, $user)) {
            ApiResponse::error('Indicação não encontrada.', 404);
        }
        Referral::markRewardPaid((int) $id);
        ApiResponse::json(['ok' => true]);
    }
}
