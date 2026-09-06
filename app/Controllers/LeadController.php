<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\FileUpload;
use App\Core\Notifier;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadStage;
use App\Models\User;

class LeadController
{
    private const EXPIRATION_WARNING_DAYS = 5;
    /** Aviso mais cedo, mais discreto -- antes so avisava 5 dias antes de expirar, o que dava
     *  pouca margem pro vendedor agir. Este soma-se ao aviso urgente, nao substitui. */
    private const EARLY_WARNING_DAYS = 15;

    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        // Sem cron nesse plano Hostinger -- lazy check a cada carga da tela de Leads, mesmo
        // padrao ja usado em Order::expireStalePending()/ReportScheduler::processDue().
        Lead::expireStaleAssignments();

        $leads = $this->scopedLeads($user);
        $stages = LeadStage::all();

        $showLicenciadoBadge = in_array($user['role_slug'], ['supervisor', 'gerente'], true);
        $now = time();
        $today = date('Y-m-d');
        $leadIds = array_column($leads, 'id');
        $pendingFollowUps = LeadNote::pendingFollowUps($leadIds);
        $notesByLead = LeadNote::forLeads($leadIds);
        foreach ($leads as &$l) {
            if ($showLicenciadoBadge) {
                $l['licenciado_name'] = User::licenciadoNameFor((int) ($l['assigned_to_user_id'] ?? 0));
            }
            $l['days_until_expiration'] = $l['expires_at']
                ? (int) ceil((strtotime($l['expires_at']) - $now) / 86400)
                : null;
            $followUpDate = $pendingFollowUps[(int) $l['id']] ?? null;
            $l['follow_up_due'] = $followUpDate !== null && $followUpDate <= $today ? $followUpDate : null;
            $l['notes'] = $notesByLead[(int) $l['id']] ?? [];
        }
        unset($l);

        $columns = [];
        foreach ($stages as $stage) {
            $columns[$stage['slug']] = array_values(array_filter($leads, fn ($l) => $l['status'] === $stage['slug']));
        }

        $isViewOnly = in_array($user['role_slug'], array_merge([Roles::SELLER], Roles::NATIONAL_SUPPORT), true);

        View::render('painel/leads/index', [
            'user' => $user,
            'stages' => $stages,
            'columns' => $columns,
            'canAssign' => !$isViewOnly,
            'isViewOnly' => $isViewOnly,
            'sellers' => !$isViewOnly ? $this->sellerOptions($user) : [],
            'showLicenciadoBadge' => $showLicenciadoBadge,
            'expirationWarningDays' => self::EXPIRATION_WARNING_DAYS,
            'earlyWarningDays' => self::EARLY_WARNING_DAYS,
        ]);
    }

    public function updateStatus(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false]);
            }
            Router::redirect('/painel/leads');
        }

        // Barreira real contra mexer no status de um lead de outra rede via POST direto (a tela
        // ja so mostra os leads do proprio escopo, mas isso e' so a UI).
        if (!in_array((int) $id, array_column($this->scopedLeads($user), 'id'), true)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false]);
            }
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        $status = $_POST['status'] ?? '';
        if (in_array($status, LeadStage::allSlugs(), true)) {
            Lead::updateStatus((int) $id, $status);
        }

        if (Response::isAjax()) {
            Response::json(['ok' => true]);
        }
        Router::redirect('/painel/leads');
    }

    public function assign(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false]);
            }
            Router::redirect('/painel/leads');
        }

        if (!in_array((int) $id, array_column($this->scopedLeads($user), 'id'), true)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false]);
            }
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        // So deixa atribuir a alguem da propria equipe -- evita "doar" um lead pra vendedor de
        // outra rede via POST direto.
        $sellerId = !empty($_POST['seller_id']) ? (int) $_POST['seller_id'] : null;
        if ($sellerId !== null && $user['role_slug'] !== 'admin'
            && !in_array($sellerId, User::downlineIds((int) $user['id']), true)) {
            Router::redirect('/painel/leads');
        }

        Lead::assignTo((int) $id, $sellerId);

        if ($sellerId !== null) {
            $lead = Lead::find((int) $id);
            if ($lead) {
                Notifier::leadRoteado($lead, $sellerId);
            }
        }

        if (Response::isAjax()) {
            Response::json(['ok' => true]);
        }
        Router::redirect('/painel/leads');
    }

    /** Timeline de observacoes (ligacoes, retorno combinado etc.) + lembrete de follow-up
     *  opcional -- pedido do usuario pra nao perder o fio da meada com muitos leads ao mesmo
     *  tempo. Uma nota nova fecha automaticamente qualquer lembrete pendente anterior desse
     *  mesmo lead (ver LeadNote::create()). */
    public function storeNote(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Response::json(['ok' => false, 'error' => 'Sessão expirada, recarregue a página.']);
        }

        if (!in_array((int) $id, array_column($this->scopedLeads($user), 'id'), true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        $note = trim($_POST['note'] ?? '');
        if ($note === '') {
            Response::json(['ok' => false, 'error' => 'Escreva a observação.']);
        }

        $followUpDate = trim($_POST['follow_up_date'] ?? '');
        if ($followUpDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $followUpDate)) {
            $followUpDate = '';
        }

        LeadNote::create((int) $id, (int) $user['id'], $note, $followUpDate !== '' ? $followUpDate : null);

        $notes = LeadNote::forLead((int) $id);
        Response::json(['ok' => true, 'notes' => $notes]);
    }

    /** Exclusao liberada pra qualquer STAFF (inclusive Gerente/Supervisor, que sao view-only pro
     * resto do CRM) -- pedido explicito, e diferente de editar dados comerciais, e mais
     * "faxina" de cadastro duplicado/invalido. */
    public function destroy(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/leads?erro=csrf');
        }

        $allowedIds = array_column($this->scopedLeads($user), 'id');
        if (!in_array($id, $allowedIds, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        Lead::delete($id);
        Router::redirect('/painel/leads?sucesso=1');
    }

    /** Justificativa do Vendedor pra nao perder o lead que esta perto (ou ja passou) do prazo de
     *  30 dias -- estende na hora (self-service, sem aprovacao), fica registrado pra auditoria
     *  em /painel/leads/extensoes. Aceita print/anexo opcional (mesmo padrao de FileUpload ja
     *  usado em Financeiro/Licenciados/documento de veiculo). */
    public function requestExtension(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        $id = (int) $id;

        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/leads?erro=1');
        }

        if (!in_array($id, array_column($this->scopedLeads($user), 'id'), true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        $justification = trim($_POST['justification'] ?? '');
        if ($justification === '') {
            Router::redirect('/painel/leads?erro=justificativa');
        }

        $attachment = null;
        try {
            $attachment = FileUpload::storeLeadExtensionAttachment($_FILES['attachment'] ?? []);
        } catch (\RuntimeException $e) {
            Router::redirect('/painel/leads?erro=' . urlencode($e->getMessage()));
        }

        Lead::requestExtension($id, (int) $user['id'], $justification, $attachment);

        Router::redirect('/painel/leads?sucesso=3');
    }

    /** Nova coluna do kanban -- compartilhada com toda a equipe (o mesmo lead e visto por gente
     * diferente dependendo do escopo, entao o status precisa ser uma unica fonte de verdade). */
    public function addStage(): void
    {
        Auth::requireRole(Roles::STAFF);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/leads?erro=csrf');
        }

        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            LeadStage::create($name);
        }

        Router::redirect('/painel/leads?sucesso=2');
    }

    /** Vendedores disponiveis pro dropdown de atribuicao, escopado por regiao -- mesmo padrao de
     *  OrderController::sellerOptions. */
    private function sellerOptions(array $user): array
    {
        $sellers = User::allByRole('vendedor');
        if ($user['role_slug'] === 'admin') {
            return $sellers;
        }

        $downline = User::downlineIds((int) $user['id']);
        return array_values(array_filter($sellers, fn ($s) => in_array((int) $s['id'], $downline, true)));
    }

    private function scopedLeads(array $user): array
    {
        $role = $user['role_slug'];

        if ($role === 'admin') {
            return Lead::all();
        }
        if ($role === Roles::SELLER) {
            return Lead::forScope(User::downlineIds((int) $user['id']), false);
        }
        if ($role === 'supervisor') {
            return Lead::forScope(User::supervisedIds((int) $user['id']), false);
        }
        if ($role === 'gerente') {
            return Lead::forScope(User::nationalIds((int) $user['id']), false);
        }
        return Lead::forScope(User::downlineIds((int) $user['id']), true);
    }
}
