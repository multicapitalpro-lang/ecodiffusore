<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\ReferralActivationCheck;
use App\Core\Router;
use App\Core\SubscriptionGate;
use App\Core\View;
use App\Models\Referral;
use App\Models\User;

/** Fase 95: Indicacoes Premiadas. O cadastro em si continua acontecendo pela tela normal de
 *  Usuarios (sem mudar esse fluxo) -- essa tela so' registra a indicacao, deixa o indicador
 *  vincular ao cadastro depois de criado, e automatiza o aviso + registro de premio quando esse
 *  cadastro vira ativo de verdade. */
class ReferralController
{
    private const ELIGIBLE_ROLES = ['licenciado', 'gestor', 'vendedor'];

    public function index(): void
    {
        Auth::requireRole(self::ELIGIBLE_ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'indicacao_premiada');

        // Sem cron nesse plano Hostinger -- roda aqui, na tela mais visitada por quem tem indicacao.
        ReferralActivationCheck::processDue();

        $referrals = Referral::forReferrer((int) $user['id']);
        $downlineIds = array_diff(User::downlineIds((int) $user['id']), [(int) $user['id']]);

        $linkable = [];
        foreach ($referrals as $r) {
            if (in_array($r['status'], ['indicado', 'em_contato'], true)) {
                $linkable[$r['id']] = Referral::linkableUsers($r, $downlineIds);
            }
        }

        View::render('painel/referrals/index', [
            'user' => $user,
            'referrals' => $referrals,
            'linkable' => $linkable,
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(self::ELIGIBLE_ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'indicacao_premiada');

        if (!Csrf::verify($_POST['csrf_token'] ?? null) || trim($_POST['name'] ?? '') === '') {
            Router::redirect('/painel/indicacoes?erro=1');
        }

        Referral::create([
            'referrer_id' => $user['id'],
            'name' => trim($_POST['name']),
            'whatsapp' => trim($_POST['whatsapp'] ?? ''),
            'target_role' => $_POST['target_role'] ?? 'vendedor',
            'notes' => trim($_POST['notes'] ?? ''),
        ]);

        Router::redirect('/painel/indicacoes?sucesso=1');
    }

    /** Quem pode gerenciar (vincular/descartar/registrar premio) uma indicacao: o proprio
     *  indicador, o Licenciado dono da rede dele, ou admin. */
    private function authorize(array $referral, array $user): bool
    {
        if ($user['role_slug'] === 'admin' || (int) $referral['referrer_id'] === (int) $user['id']) {
            return true;
        }
        $licenciado = User::licenciadoFor((int) $referral['referrer_id']);
        return $licenciado !== null && (int) $licenciado['id'] === (int) $user['id'];
    }

    public function link(string $id): void
    {
        Auth::requireRole(self::ELIGIBLE_ROLES);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'indicacao_premiada');

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/indicacoes?erro=1');
        }

        $referral = Referral::find((int) $id);
        if (!$referral || !$this->authorize($referral, $user)) {
            Router::redirect('/painel/indicacoes?erro=1');
        }

        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $downlineIds = array_diff(User::downlineIds((int) $referral['referrer_id']), [(int) $referral['referrer_id']]);
        $candidates = array_column(Referral::linkableUsers($referral, $downlineIds), 'id');
        if (!$targetUserId || !in_array($targetUserId, $candidates, true)) {
            Router::redirect('/painel/indicacoes?erro=1');
        }

        Referral::linkToUser((int) $id, $targetUserId);
        Router::redirect('/painel/indicacoes?sucesso=1');
    }

    public function markContacted(string $id): void
    {
        Auth::requireRole(self::ELIGIBLE_ROLES);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/indicacoes');
        }

        $referral = Referral::find((int) $id);
        if ($referral && $this->authorize($referral, $user)) {
            Referral::markContacted((int) $id);
        }

        Router::redirect('/painel/indicacoes?sucesso=1');
    }

    public function discard(string $id): void
    {
        Auth::requireRole(self::ELIGIBLE_ROLES);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/indicacoes');
        }

        $referral = Referral::find((int) $id);
        if ($referral && $this->authorize($referral, $user)) {
            Referral::markDiscarded((int) $id);
        }

        Router::redirect('/painel/indicacoes?sucesso=1');
    }

    public function setReward(string $id): void
    {
        Auth::requireRole(self::ELIGIBLE_ROLES);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/indicacoes');
        }

        $referral = Referral::find((int) $id);
        if ($referral && $this->authorize($referral, $user)) {
            $amount = is_numeric($_POST['reward_amount'] ?? null) ? (float) $_POST['reward_amount'] : null;
            Referral::setReward((int) $id, trim($_POST['reward_description'] ?? ''), $amount);
        }

        Router::redirect('/painel/indicacoes?sucesso=1');
    }

    public function markRewardPaid(string $id): void
    {
        Auth::requireRole(self::ELIGIBLE_ROLES);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/indicacoes');
        }

        $referral = Referral::find((int) $id);
        if ($referral && $this->authorize($referral, $user)) {
            Referral::markRewardPaid((int) $id);
        }

        Router::redirect('/painel/indicacoes?sucesso=1');
    }
}
