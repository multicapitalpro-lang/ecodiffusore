<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Notifier;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\ScreenPermissions;
use App\Core\SubscriptionPlans;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Commission;
use App\Models\LicenciadoSeatAddon;
use App\Models\Order;
use App\Models\PricingTier;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCommissionTier;

class UserController
{
    public function index(): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();

        if ($user['role_slug'] === 'admin') {
            $scoped = User::all();
        } else {
            $ids = $this->scopedUserIds($user);
            $scoped = array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $ids, true)));
        }

        // Cards do topo contam sempre o total do escopo, sem aplicar os filtros abaixo.
        $stats = ['cliente' => 0, 'licenciado' => 0, 'gestor' => 0, 'vendedor' => 0];
        foreach ($scoped as $u) {
            if (isset($stats[$u['role_slug']])) {
                $stats[$u['role_slug']]++;
            }
        }

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'role' => $_GET['role'] ?? '',
            'status' => $_GET['status'] ?? '',
            'onboarding' => $_GET['onboarding'] ?? '',
            'city' => trim($_GET['city'] ?? ''),
            'state' => trim($_GET['state'] ?? ''),
        ];

        $users = array_values(array_filter($scoped, function ($u) use ($filters) {
            if ($filters['q'] !== '' && stripos($u['name'] . ' ' . $u['email'], $filters['q']) === false) {
                return false;
            }
            if ($filters['role'] !== '' && $u['role_slug'] !== $filters['role']) {
                return false;
            }
            if ($filters['status'] !== '' && $u['status'] !== $filters['status']) {
                return false;
            }
            if ($filters['onboarding'] !== '' && ($u['licenciado_onboarding_status'] ?? 'nao_aplicavel') !== $filters['onboarding']) {
                return false;
            }
            if ($filters['city'] !== '' && stripos((string) $u['city'], $filters['city']) === false) {
                return false;
            }
            if ($filters['state'] !== '' && strcasecmp((string) $u['state'], $filters['state']) !== 0) {
                return false;
            }
            return true;
        }));

        foreach ($users as &$u) {
            $u['responsavel'] = User::responsibleFor($u);
        }
        unset($u);

        // Fase 79: organograma em pirâmide (sanfona) -- pedido explicito do usuario: clicar num
        // Licenciado mostra Gestores/Vendedores da rede dele; clicar num Vendedor mostra os
        // Clientes vinculados. Agrupa por manager_id sobre TODO o escopo (nao so os filtrados),
        // pra sanfona nao sumir com um filtro ativo na tabela principal.
        $byManager = [];
        foreach ($scoped as $u) {
            $byManager[(int) ($u['manager_id'] ?? 0)][] = $u;
        }

        View::render('painel/users/index', [
            'user' => $user,
            'users' => $users,
            'stats' => $stats,
            'filters' => $filters,
            'roles' => Role::all(),
            'byManager' => $byManager,
        ]);
    }

    /** Fase 79: clientes vinculados a um Vendedor/Gestor/Licenciado -- fragmento HTML carregado
     *  via fetch() quando a sanfona de um vendedor e' aberta pela primeira vez (lazy, pra nao
     *  consultar clientes de todo mundo de uma vez so). Mesmo escopo de acesso de
     *  ClientController (isAuthorizedTarget), aplicado sobre o VENDEDOR (nao sobre quem esta
     *  pedindo), senao um Licenciado nao conseguiria ver os clientes do proprio vendedor. */
    public function clientsForSeller(string $id): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();
        $id = (int) $id;

        $target = User::find($id);
        if (!$target || !$this->isAuthorizedTarget($user, $id)) {
            http_response_code(403);
            exit;
        }

        View::render('painel/users/_clients_fragment', [
            'clients' => Client::all(['seller_id' => $id]),
        ], null);
    }

    public function create(): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();

        $isFragment = isset($_GET['fragment']);

        View::render('painel/users/form', [
            'user' => $user,
            'roles' => $this->creatableRoles($user),
            'managers' => $this->managerOptions($user, null),
            'supervisors' => $this->supervisorOptions($user),
            'canSetCommission' => $this->canSetCommission($user),
            'pricingTiers' => PricingTier::all(),
            'vendorTierValues' => [],
            'allowedScreens' => null,
            'editing' => null,
            'errors' => [],
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function store(): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['name' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect('/painel/usuarios/novo?erro=1');
        }

        $errors = $this->validate($_POST, null, $user);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            View::render('painel/users/form', [
                'user' => $user,
                'roles' => $this->creatableRoles($user),
                'managers' => $this->managerOptions($user, null),
                'supervisors' => $this->supervisorOptions($user),
                'canSetCommission' => $this->canSetCommission($user),
                'pricingTiers' => PricingTier::all(),
                'vendorTierValues' => $this->tierValuesFromPost($_POST),
                'allowedScreens' => $_POST['screens'] ?? [],
                'editing' => null,
                'errors' => $errors,
                'old' => $_POST,
            ]);
            return;
        }

        $managerId = $this->resolveManagerId($user, $_POST, null);
        $commissionPct = $this->canSetCommission($user) && !empty($_POST['commission_pct']) ? $_POST['commission_pct'] : null;

        $createdRoleSlug = null;
        foreach (Role::all() as $r) {
            if ((int) $r['id'] === (int) $_POST['role_id']) {
                $createdRoleSlug = $r['slug'];
                break;
            }
        }

        $vendorType = $this->vendorCommissionType($user, $createdRoleSlug, $_POST);

        // Fase 86: cadastrar mais um Gestor/Vendedor ATIVO pode estourar o limite de colaboradores
        // inclusos na assinatura -- checa ANTES de criar, manda pra tela de comprar vaga extra em
        // vez de deixar passar. So vale pra status ativo (inativo nao ocupa vaga).
        $newStatus = $_POST['status'] ?? 'active';
        if (in_array($createdRoleSlug, ['gestor', 'vendedor'], true) && $newStatus === 'active') {
            $licenciadoId = User::licenciadoIdFor($managerId);
            if ($licenciadoId && $this->seatLimitReached($licenciadoId)) {
                if (Response::isAjax()) {
                    Response::json(['ok' => false, 'errors' => ['_geral' => 'Limite de colaboradores da assinatura atingido. Compre uma vaga extra pra cadastrar mais um.']]);
                }
                Router::redirect('/painel/assinatura?erro=limite');
            }
        }

        // Fase 56: comissao fixa do Influenciador -- so' admin define, R$100 default (pedido
        // explicito do usuario) se ele nao digitar um valor customizado.
        $influencerCommissionValue = $createdRoleSlug === Roles::INFLUENCER
            ? ($user['role_slug'] === 'admin' && ($_POST['influencer_commission_value'] ?? '') !== '' ? $_POST['influencer_commission_value'] : 100)
            : null;

        $newUserId = User::create([
            'role_id' => (int) $_POST['role_id'],
            'manager_id' => $managerId,
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'whatsapp' => trim($_POST['whatsapp'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'password' => $_POST['password'],
            'status' => $_POST['status'] ?? 'active',
            'commission_pct' => $commissionPct,
            'influencer_commission_value' => $influencerCommissionValue,
            'commission_type' => $vendorType,
            'must_change_password' => true,
            'licenciado_onboarding_status' => $createdRoleSlug === 'licenciado' ? 'aguardando_perfil' : 'nao_aplicavel',
        ]);

        if (in_array($createdRoleSlug, ['vendedor', 'supervisor'], true) && $this->canSetCommission($user)) {
            UserCommissionTier::setForUser($newUserId, $this->vendorTierValues($_POST));
        }

        if (in_array($createdRoleSlug, ['gestor', 'vendedor'], true)) {
            ScreenPermissions::setFor($newUserId, $_POST['screens'] ?? []);

            // Fase 32: Gestor/Vendedor recebe as condicoes de comissao no WhatsApp e confirma com
            // 1 clique, sem precisar logar -- pedido explicito do usuario, "fica salvo em sistema
            // essa informacao".
            $token = User::generateCommissionAcceptToken($newUserId);
            Notifier::propostaComissaoCriada(User::find($newUserId), $token);
        }

        if ($createdRoleSlug === 'licenciado') {
            // Fase 67: codigo proprio (LIC-0001...) pra Gerente/Admin acharem esse licenciado
            // rapido em /painel/licenciados.
            User::assignLicenciadoCode($newUserId);

            // Supervisor cadastrando o proprio Licenciado ja assume a supervisao na hora -- evita
            // um passo manual redundante. Admin/Gerente podem escolher o supervisor direto no
            // formulario agora, em vez de precisar ir na tela /painel/licenciados depois.
            if ($user['role_slug'] === 'supervisor') {
                User::setSupervisor($newUserId, (int) $user['id']);
            } else {
                $supervisorId = $this->resolveSupervisorId($user, $_POST);
                if ($supervisorId !== null) {
                    User::setSupervisor($newUserId, $supervisorId);
                }
            }
        }

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => '/painel/usuarios?sucesso=1']);
        }

        Router::redirect('/painel/usuarios?sucesso=1');
    }

    public function edit(string $id): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();
        $id = (int) $id;

        $editing = User::find($id);
        if (!$editing) {
            Router::redirect('/painel/usuarios');
        }

        $this->authorizeTarget($user, $id);

        // ?fragment=1 -- renderiza so o <form>, sem o layout do painel, pro modal de edicao
        // carregar via fetch() sem precisar de uma pagina cheia (ver painel.js e users/index.php).
        $isFragment = isset($_GET['fragment']);

        View::render('painel/users/form', [
            'user' => $user,
            'roles' => $this->rolesForEditing($user, $editing),
            'managers' => $this->managerOptions($user, $id),
            'supervisors' => $this->supervisorOptions($user),
            'canSetCommission' => $this->canSetCommission($user),
            'pricingTiers' => PricingTier::all(),
            'vendorTierValues' => UserCommissionTier::forUser($id),
            'allowedScreens' => ScreenPermissions::getFor($id),
            'editing' => $editing,
            'errors' => [],
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function update(string $id): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();
        $id = (int) $id;

        $this->authorizeTarget($user, $id);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['name' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect("/painel/usuarios/{$id}/editar?erro=1");
        }

        $errors = $this->validate($_POST, $id, $user);

        $managerId = $this->resolveManagerId($user, $_POST, $id);
        if ($managerId !== null && $this->createsCycle($id, $managerId)) {
            $errors['manager_id'] = 'Essa escolha criaria um ciclo na hierarquia (ex: A reporta pra B que reporta pra A).';
        }

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            View::render('painel/users/form', [
                'user' => $user,
                'roles' => $this->rolesForEditing($user, User::find($id)),
                'managers' => $this->managerOptions($user, $id),
                'supervisors' => $this->supervisorOptions($user),
                'canSetCommission' => $this->canSetCommission($user),
                'pricingTiers' => PricingTier::all(),
                'vendorTierValues' => $this->tierValuesFromPost($_POST),
                'allowedScreens' => $_POST['screens'] ?? [],
                'editing' => array_merge(['id' => $id], $_POST),
                'errors' => $errors,
            ]);
            return;
        }

        $before = User::find($id);

        $commissionPct = $this->canSetCommission($user) && !empty($_POST['commission_pct']) ? $_POST['commission_pct'] : ($before['commission_pct'] ?? null);
        $discountLimitPct = $_POST['discount_limit_pct'] !== '' ? $_POST['discount_limit_pct'] : null;

        $editedRoleSlug = null;
        foreach (Role::all() as $r) {
            if ((int) $r['id'] === (int) $_POST['role_id']) {
                $editedRoleSlug = $r['slug'];
                break;
            }
        }

        $vendorType = $this->vendorCommissionType($user, $editedRoleSlug, $_POST);

        // Fase 86: so checa limite quando essa edicao PASSA A OCUPAR uma vaga que antes nao
        // ocupava (reativar um Gestor/Vendedor inativo, ou trocar o papel de outra coisa pra
        // Gestor/Vendedor) -- editar quem ja era Gestor/Vendedor ativo nao consome vaga nova.
        $editedStatus = $_POST['status'] ?? $before['status'];
        $wasSeat = $before['status'] === 'active' && in_array($before['role_slug'], ['gestor', 'vendedor'], true);
        $willBeSeat = $editedStatus === 'active' && in_array($editedRoleSlug, ['gestor', 'vendedor'], true);
        if (!$wasSeat && $willBeSeat) {
            $licenciadoId = User::licenciadoIdFor($managerId);
            if ($licenciadoId && $this->seatLimitReached($licenciadoId)) {
                if (Response::isAjax()) {
                    Response::json(['ok' => false, 'errors' => ['_geral' => 'Limite de colaboradores da assinatura atingido. Compre uma vaga extra antes de ativar mais um.']]);
                }
                Router::redirect('/painel/assinatura?erro=limite');
            }
        }

        // Fase 56: so' admin edita a comissao fixa do Influenciador -- qualquer outro editor
        // (ou o campo ausente do POST) preserva o valor que ja estava salvo, nunca zera.
        $influencerCommissionValue = $user['role_slug'] === 'admin' && ($_POST['influencer_commission_value'] ?? '') !== ''
            ? $_POST['influencer_commission_value']
            : ($before['influencer_commission_value'] ?? null);

        User::update($id, [
            'role_id' => (int) $_POST['role_id'],
            'manager_id' => $managerId,
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'whatsapp' => trim($_POST['whatsapp'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'status' => $_POST['status'] ?? 'active',
            'commission_pct' => $commissionPct,
            'influencer_commission_value' => $influencerCommissionValue,
            'commission_type' => $vendorType,
            'discount_limit_pct' => $discountLimitPct,
        ]);

        if (in_array($editedRoleSlug, ['vendedor', 'supervisor'], true) && $this->canSetCommission($user)) {
            UserCommissionTier::setForUser($id, $this->vendorTierValues($_POST));
        } elseif (!in_array($editedRoleSlug, ['vendedor', 'supervisor'], true)) {
            // Papel deixou de ser vendedor/supervisor -- limpa qualquer faixa configurada antes.
            UserCommissionTier::setForUser($id, []);
        }

        if (in_array($editedRoleSlug, ['gestor', 'vendedor'], true)) {
            ScreenPermissions::setFor($id, $_POST['screens'] ?? []);
        }

        // Fase 67: papel virou Licenciado agora (era outra coisa antes) e ainda nao tem codigo --
        // cobre a troca de papel, ja que store() so cobre o cadastro direto como Licenciado.
        if ($editedRoleSlug === 'licenciado' && empty($before['licenciado_code'])) {
            User::assignLicenciadoCode($id);
        }

        $this->logIfChanged($before, 'commission_pct', $commissionPct, $id);
        $this->logIfChanged($before, 'discount_limit_pct', $discountLimitPct, $id);
        $this->logIfChanged($before, 'manager_id', $managerId, $id);
        $this->logIfChanged($before, 'commission_type', $vendorType, $id);

        if ($before['role_slug'] === 'licenciado' && in_array($user['role_slug'], Roles::SUPERVISOR_ASSIGNMENT, true)) {
            $supervisorId = $this->resolveSupervisorId($user, $_POST);
            if ((string) ($before['supervisor_id'] ?? '') !== (string) $supervisorId) {
                User::setSupervisor($id, $supervisorId);
                AuditLog::record((int) $user['id'], 'licenciado_supervisor_alterado', 'user', $id, ['supervisor_id' => $before['supervisor_id'] ?? null], ['supervisor_id' => $supervisorId]);
            }
        }

        if (!empty($_POST['reset_password'])) {
            $temp = substr(bin2hex(random_bytes(6)), 0, 10);
            User::resetPassword($id, $temp);
            $target = "/painel/usuarios?sucesso=2&temp={$temp}";
        } else {
            $target = '/painel/usuarios?sucesso=1';
        }

        // Fase 87: e-mail do ClickSign fica "congelado" no signatario desde a criacao do envelope
        // -- corrigir o e-mail aqui no cadastro NAO atualiza o que o ClickSign tem guardado (bug
        // real reportado ao vivo: token de autenticacao continuou indo pro e-mail antigo/errado
        // mesmo depois do cadastro corrigido). Avisa o admin que precisa clicar em "Pedir nova
        // assinatura" (LicenciadoApprovalController::resendSignature) pra gerar um envelope novo
        // com o e-mail certo -- so quando o e-mail de fato mudou e o Licenciado ainda esta preso
        // no fluxo de assinatura (depois de 'ativo' o contrato ja foi assinado, nao adianta mais).
        if ($before['role_slug'] === 'licenciado' && trim($_POST['email']) !== $before['email']
            && ($before['licenciado_onboarding_status'] ?? '') === 'aguardando_assinatura') {
            $target .= '&aviso=email_licenciado_pendente';
        }

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }

        Router::redirect($target);
    }

    public function destroy(string $id): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/usuarios?erro=csrf');
        }

        $this->authorizeTarget($user, $id);

        $reason = $this->blockDeletion($user, $id);
        if ($reason !== null) {
            Router::redirect('/painel/usuarios?erro=' . $reason);
        }

        try {
            User::delete($id);
        } catch (\PDOException $e) {
            Router::redirect('/painel/usuarios?erro=vinculo');
        }

        AuditLog::record((int) $user['id'], 'usuario_excluido', 'user', $id, [], []);
        Router::redirect('/painel/usuarios?sucesso=3');
    }

    /** Exclusao em lote -- ids fora do escopo/protegidos sao pulados, nunca derrubam o restante. */
    public function destroyBulk(): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/usuarios?erro=csrf');
        }

        $ids = array_unique(array_map('intval', $_POST['ids'] ?? []));
        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            if (!$this->isAuthorizedTarget($user, $id) || $this->blockDeletion($user, $id) !== null) {
                $failed++;
                continue;
            }
            try {
                User::delete($id);
                AuditLog::record((int) $user['id'], 'usuario_excluido', 'user', $id, [], []);
                $deleted++;
            } catch (\PDOException $e) {
                $failed++;
            }
        }

        Router::redirect("/painel/usuarios?sucesso=4&deletados={$deleted}&falhas={$failed}");
    }

    /** Motivo (ou null se pode excluir) -- nunca deixa excluir a si mesmo nem um Admin por aqui. */
    private function blockDeletion(array $user, int $targetId): ?string
    {
        $target = User::find($targetId);
        if (!$target) {
            return 'naoencontrado';
        }
        if ($targetId === (int) $user['id']) {
            return 'self';
        }
        if ($target['role_slug'] === 'admin') {
            return 'admin';
        }
        return null;
    }

    /** creatableRoles() + o papel atual de quem esta sendo editado, mesmo que quem edita nao
     * possa ATRIBUIR esse papel a outra pessoa (ex: Gerente editando o proprio cadastro, que e
     * "gerente" -- papel fora do que um Gerente pode escolher pra alguem). Sem isso o <select>
     * fica sem nenhuma opcao selecionada e o form trava no "Selecione um item da lista" do
     * navegador mesmo com os outros campos corretos. validate() ja permite manter o papel atual
     * (ver "keepsOwnRole") -- isso so garante que a opcao exista na lista pra começar. */
    private function rolesForEditing(array $user, ?array $editing): array
    {
        $roles = $this->creatableRoles($user);
        if ($editing === null) {
            return $roles;
        }

        $currentRoleId = (int) ($editing['role_id'] ?? 0);
        $hasCurrentRole = in_array($currentRoleId, array_map(fn ($r) => (int) $r['id'], $roles), true);

        if (!$hasCurrentRole && $currentRoleId > 0) {
            $currentRole = current(array_filter(Role::all(), fn ($r) => (int) $r['id'] === $currentRoleId));
            if ($currentRole) {
                $roles[] = $currentRole;
            }
        }

        return $roles;
    }

    /** Papeis que quem esta logado tem permissao de atribuir a um novo/editado usuario */
    private function creatableRoles(array $user): array
    {
        $all = Role::all();

        if ($user['role_slug'] === 'admin') {
            return $all;
        }
        if ($user['role_slug'] === Roles::REGIONAL_OWNER) {
            return array_values(array_filter($all, fn ($r) => in_array($r['slug'], ['gestor', 'vendedor'], true)));
        }
        if ($user['role_slug'] === 'gerente') {
            return array_values(array_filter($all, fn ($r) => in_array($r['slug'], ['supervisor', 'licenciado'], true)));
        }
        if ($user['role_slug'] === 'supervisor') {
            return array_values(array_filter($all, fn ($r) => $r['slug'] === 'licenciado'));
        }

        // gestor: so pode cadastrar vendedor
        return array_values(array_filter($all, fn ($r) => $r['slug'] === 'vendedor'));
    }

    /** Opcoes de "reporta para" restritas a propria regiao (nunca deixa escolher fora dela) */
    private function managerOptions(array $user, ?int $editingId): array
    {
        if ($user['role_slug'] === 'admin') {
            return User::managerCandidates($editingId);
        }
        if ($user['role_slug'] === Roles::REGIONAL_OWNER) {
            $downline = User::downlineIds((int) $user['id']);
            $gestores = array_values(array_filter(User::allByRole('gestor'), fn ($g) => in_array((int) $g['id'], $downline, true)));
            $gestores = array_map(fn ($g) => $g + ['role_slug' => 'gestor'], $gestores);
            return array_merge([['id' => $user['id'], 'name' => $user['name'], 'role_slug' => 'licenciado']], $gestores);
        }
        if ($user['role_slug'] === 'gerente') {
            return [['id' => $user['id'], 'name' => $user['name'], 'role_slug' => 'gerente']];
        }
        if ($user['role_slug'] === 'supervisor') {
            return [['id' => $user['id'], 'name' => $user['name'], 'role_slug' => 'supervisor']];
        }

        // gestor: unica opcao e ele mesmo
        return [['id' => $user['id'], 'name' => $user['name'], 'role_slug' => 'gestor']];
    }

    /**
     * Supervisores que quem esta logado pode atribuir a um Licenciado (campo separado de
     * "reporta para" -- supervisor_id nao e manager_id, ver Fase 12). So Admin/Gerente atribuem;
     * Gerente so pode indicar supervisor da propria equipe (mesma regra de assignSupervisor()).
     */
    private function supervisorOptions(array $user): array
    {
        if (!in_array($user['role_slug'], Roles::SUPERVISOR_ASSIGNMENT, true)) {
            return [];
        }

        $supervisors = User::allByRole('supervisor');
        if ($user['role_slug'] === 'gerente') {
            $supervisors = array_values(array_filter($supervisors, fn ($s) => (int) $s['manager_id'] === (int) $user['id']));
        }

        return $supervisors;
    }

    private function resolveSupervisorId(array $user, array $input): ?int
    {
        if (empty($input['supervisor_id'])) {
            return null;
        }

        $allowed = array_map(fn ($s) => (int) $s['id'], $this->supervisorOptions($user));
        $chosen = (int) $input['supervisor_id'];

        return in_array($chosen, $allowed, true) ? $chosen : null;
    }

    /**
     * So relevante quando o papel cadastrado/editado e' vendedor OU supervisor -- comissao por
     * faixa de preco negociado (pricing_tiers), em % da venda ou valor fixo por venda. Pro
     * Vendedor, definida pelo Licenciado (ou admin), sai do pool do Licenciado; pro Supervisor
     * (Fase 83), definida pelo Gerente (ou admin), sai direto da Ecodiffusore (comissao
     * nacional), mesmo tratamento de Commission::createCascadeForOrder. Quando o papel nao e'
     * nenhum dos dois, ou quem esta logado nao pode definir comissao, devolve null (limpa o tipo
     * -- os valores por faixa sao limpos a parte, ver UserCommissionTier::setForUser() nas
     * chamadas de store()/update()).
     */
    private function vendorCommissionType(array $user, ?string $roleSlug, array $input): ?string
    {
        if (!$this->canSetCommission($user) || !in_array($roleSlug, ['vendedor', 'supervisor'], true)) {
            return null;
        }

        return in_array($input['commission_type'] ?? '', ['percentual', 'fixo'], true) ? $input['commission_type'] : null;
    }

    /** [pricing_tier_id => float|null], a partir dos campos commission_tier_{id} do POST --
     *  usado pra gravar em UserCommissionTier::setForUser(). */
    private function vendorTierValues(array $input): array
    {
        $values = [];
        foreach (PricingTier::all() as $tier) {
            $raw = $input['commission_tier_' . $tier['id']] ?? '';
            $values[(int) $tier['id']] = $raw !== '' ? (float) $raw : null;
        }
        return $values;
    }

    /** Mesma leitura de vendorTierValues(), mas mantendo os valores como string (ou '') pra
     *  repopular o formulario quando a validacao falha, sem perder o que a pessoa digitou. */
    private function tierValuesFromPost(array $input): array
    {
        $values = [];
        foreach (PricingTier::all() as $tier) {
            $values[(int) $tier['id']] = $input['commission_tier_' . $tier['id']] ?? '';
        }
        return $values;
    }

    /**
     * So admin e licenciado definem commission_pct de alguem: licenciado controla o quanto repassa
     * do proprio pool (gestor/vendedor); admin define o % contratual do licenciado e o % de
     * gerente/supervisor (pago direto pela Ecodiffusore). Gestor nunca define comissao. Fase 83:
     * Gerente tambem define a comissao do Supervisor que ele cadastra (pedido explicito do
     * usuario -- mesma liberdade que o Licenciado ja tem pro Vendedor, incluindo a tabela por
     * faixa abaixo), continua sem poder mexer na do proprio Licenciado (essa vem da faixa de
     * preco, ver licenciado-commission-note).
     */
    /** Fase 86: true se a rede desse Licenciado ja esta no limite de colaboradores (assinatura
     *  base + vagas extras compradas) -- vale pra qualquer criador (Admin incluso: mesmo o Admin
     *  cadastrando em nome do Licenciado, a vaga e' real e precisa ser paga). */
    private function seatLimitReached(int $licenciadoId): bool
    {
        $allowed = SubscriptionPlans::INCLUDED_SEATS + LicenciadoSeatAddon::activeSeatsFor($licenciadoId);
        return User::activeStaffCountFor($licenciadoId) >= $allowed;
    }

    private function canSetCommission(array $user): bool
    {
        return in_array($user['role_slug'], ['admin', Roles::REGIONAL_OWNER, 'gerente'], true);
    }

    private function resolveManagerId(array $user, array $input, ?int $targetId): ?int
    {
        if ($user['role_slug'] === 'admin') {
            return !empty($input['manager_id']) ? (int) $input['manager_id'] : null;
        }

        // Editando o proprio cadastro: mantem o manager_id que ja tinha (nao faz sentido
        // "trocar quem reporta pra si mesmo" pela propria tela de edicao).
        if ($targetId !== null && $targetId === (int) $user['id']) {
            $current = User::find($targetId);
            return $current && $current['manager_id'] !== null ? (int) $current['manager_id'] : null;
        }

        if (in_array($user['role_slug'], ['gestor', 'gerente', 'supervisor'], true)) {
            return (int) $user['id'];
        }

        // licenciado: so aceita a si mesmo ou um dos proprios gestores
        $allowed = array_map(fn ($m) => (int) $m['id'], $this->managerOptions($user, null));
        $chosen = !empty($input['manager_id']) ? (int) $input['manager_id'] : (int) $user['id'];
        return in_array($chosen, $allowed, true) ? $chosen : (int) $user['id'];
    }

    /** Bloqueia edicao/leitura de gente fora da propria regiao/equipe */
    private function isAuthorizedTarget(array $user, int $targetId): bool
    {
        return $user['role_slug'] === 'admin' || in_array($targetId, $this->scopedUserIds($user), true);
    }

    /** Fase 79c: Gerente/Supervisor usavam downlineIds() (segue manager_id) igual Gestor/
     *  Licenciado -- mas Gerente/Supervisor sao rede NACIONAL, ligada via supervisor_id, nao
     *  manager_id (mesmo criterio ja usado em DashboardController/PerformanceController/
     *  FinanceController). Por isso Gerente via quase ninguem em /painel/usuarios -- bug
     *  reportado pelo usuario ("gerente teria o mesmo acesso de admin praticamente"). */
    private function scopedUserIds(array $user): array
    {
        if ($user['role_slug'] === 'gerente') {
            return User::nationalIds((int) $user['id']);
        }
        if ($user['role_slug'] === 'supervisor') {
            return User::supervisedIds((int) $user['id']);
        }
        return User::downlineIds((int) $user['id']);
    }

    private function authorizeTarget(array $user, int $targetId): void
    {
        if (!$this->isAuthorizedTarget($user, $targetId)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }
    }

    private function logIfChanged(array $before, string $field, mixed $newValue, int $userId): void
    {
        $oldValue = $before[$field] ?? null;
        if ((string) $oldValue === (string) $newValue) {
            return;
        }

        AuditLog::record(
            (int) Auth::user()['id'],
            "usuario_{$field}_alterado",
            'user',
            $userId,
            [$field => $oldValue],
            [$field => $newValue]
        );
    }

    private function createsCycle(int $userId, int $candidateManagerId): bool
    {
        if ($candidateManagerId === $userId) {
            return true;
        }

        foreach (User::managerChain($candidateManagerId) as $manager) {
            if ((int) $manager['id'] === $userId) {
                return true;
            }
        }

        return false;
    }

    private function validate(array $input, ?int $exceptId, array $creator): array
    {
        $errors = [];

        if (trim($input['name'] ?? '') === '') {
            $errors['name'] = 'Informe o nome.';
        }

        $email = trim($input['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        } elseif (User::emailExists($email, $exceptId)) {
            $errors['email'] = 'Já existe um usuário com este e-mail.';
        }

        if (empty($input['role_id'])) {
            $errors['role_id'] = 'Selecione um papel.';
        } else {
            $allowedRoleIds = array_map(fn ($r) => (int) $r['id'], $this->creatableRoles($creator));
            // Editar o proprio cadastro sem trocar o papel atual e sempre permitido (nao exige
            // permissao de "atribuir" um papel que a pessoa ja tem).
            $currentRoleId = $exceptId !== null ? (int) (User::find($exceptId)['role_id'] ?? 0) : null;
            $keepsOwnRole = $currentRoleId !== null && $currentRoleId === (int) $input['role_id'];

            if (!$keepsOwnRole && !in_array((int) $input['role_id'], $allowedRoleIds, true)) {
                $errors['role_id'] = 'Você não tem permissão para atribuir este papel.';
            }
        }

        if ($exceptId === null && strlen($input['password'] ?? '') < 8) {
            $errors['password'] = 'A senha precisa ter pelo menos 8 caracteres.';
        }

        $commissionType = $input['commission_type'] ?? '';
        if ($commissionType !== '' && in_array($commissionType, ['percentual', 'fixo'], true)) {
            foreach (PricingTier::all() as $tier) {
                $field = 'commission_tier_' . $tier['id'];
                $value = $input[$field] ?? '';
                if ($value === '') {
                    continue;
                }
                if (!is_numeric($value) || (float) $value < 0) {
                    $errors[$field] = 'Informe um valor válido.';
                } elseif ($commissionType === 'percentual' && (float) $value > 100) {
                    $errors[$field] = 'Percentual não pode passar de 100%.';
                }
            }
        }

        return $errors;
    }

    public function licenciados(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $user = Auth::user();

        $supervisors = User::allByRole('supervisor');
        if ($user['role_slug'] === 'gerente') {
            $supervisors = array_values(array_filter($supervisors, fn ($s) => (int) $s['manager_id'] === (int) $user['id']));
        }

        $allLicenciados = User::allByRole('licenciado');

        $stats = ['total' => count($allLicenciados), 'ativos' => 0, 'sem_supervisor' => 0, 'por_status' => []];
        foreach ($allLicenciados as $l) {
            $status = $l['licenciado_onboarding_status'] ?? 'nao_aplicavel';
            $stats['por_status'][$status] = ($stats['por_status'][$status] ?? 0) + 1;
            if ($status === 'ativo') {
                $stats['ativos']++;
            }
            if (empty($l['supervisor_id'])) {
                $stats['sem_supervisor']++;
            }
        }

        $statusFilter = trim($_GET['status'] ?? '');
        $licenciados = $statusFilter !== ''
            ? array_values(array_filter($allLicenciados, fn ($l) => ($l['licenciado_onboarding_status'] ?? 'nao_aplicavel') === $statusFilter))
            : $allLicenciados;

        // Resumo de vendas/comissao direto na linha -- conecta essa tela (que so servia pra
        // atribuir supervisor) ao resto do desempenho, sem precisar abrir outra pagina.
        $licenciadoIds = array_column($allLicenciados, 'id');
        $commissionTotals = [];
        foreach (Commission::byBeneficiary(['beneficiary_ids' => $licenciadoIds]) as $row) {
            $commissionTotals[(int) $row['beneficiary_id']] = $row;
        }

        $vendasTotals = [];
        foreach ($allLicenciados as $l) {
            $downline = User::downlineIds((int) $l['id']);
            $vendasTotals[(int) $l['id']] = Order::metrics('2000-01-01', date('Y-m-d'), null, $downline);
        }

        View::render('painel/users/licenciados', [
            'user' => $user,
            'licenciados' => $licenciados,
            'supervisors' => $supervisors,
            'stats' => $stats,
            'statusFilter' => $statusFilter,
            'commissionTotals' => $commissionTotals,
            'vendasTotals' => $vendasTotals,
        ]);
    }

    /** Autorizacao compartilhada entre assignSupervisor() (1 licenciado) e assignSupervisorBulk()
     *  (varios de uma vez) -- Gerente so pode apontar pra um Supervisor da propria equipe, sem
     *  isso um Gerente poderia atribuir licenciados a supervisor de outro Gerente via POST direto. */
    private function authorizeSupervisorTarget(array $user, ?int $supervisorId): bool
    {
        if ($supervisorId === null) {
            return true;
        }

        $supervisor = User::find($supervisorId);
        if (!$supervisor || $supervisor['role_slug'] !== 'supervisor') {
            return false;
        }

        return $user['role_slug'] === 'admin' || (int) $supervisor['manager_id'] === (int) $user['id'];
    }

    public function assignSupervisor(string $id): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $user = Auth::user();
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/licenciados?erro=1');
        }

        $licenciado = User::find($id);
        if (!$licenciado || $licenciado['role_slug'] !== 'licenciado') {
            Router::redirect('/painel/licenciados?erro=1');
        }

        $supervisorId = !empty($_POST['supervisor_id']) ? (int) $_POST['supervisor_id'] : null;

        if (!$this->authorizeSupervisorTarget($user, $supervisorId)) {
            Router::redirect('/painel/licenciados?erro=1');
        }

        $before = $licenciado['supervisor_id'] ?? null;
        User::setSupervisor($id, $supervisorId);
        if ((string) $before !== (string) $supervisorId) {
            AuditLog::record((int) $user['id'], 'licenciado_supervisor_alterado', 'user', $id, ['supervisor_id' => $before], ['supervisor_id' => $supervisorId]);
        }

        Router::redirect('/painel/licenciados?sucesso=1');
    }

    public function assignSupervisorBulk(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/licenciados?erro=1');
        }

        $ids = array_map('intval', $_POST['licenciado_ids'] ?? []);
        if (!$ids) {
            Router::redirect('/painel/licenciados?erro=1');
        }

        $supervisorId = !empty($_POST['supervisor_id']) ? (int) $_POST['supervisor_id'] : null;

        if (!$this->authorizeSupervisorTarget($user, $supervisorId)) {
            Router::redirect('/painel/licenciados?erro=1');
        }

        foreach ($ids as $id) {
            $licenciado = User::find($id);
            if (!$licenciado || $licenciado['role_slug'] !== 'licenciado') {
                continue;
            }

            $before = $licenciado['supervisor_id'] ?? null;
            User::setSupervisor($id, $supervisorId);
            if ((string) $before !== (string) $supervisorId) {
                AuditLog::record((int) $user['id'], 'licenciado_supervisor_alterado', 'user', $id, ['supervisor_id' => $before], ['supervisor_id' => $supervisorId]);
            }
        }

        Router::redirect('/painel/licenciados?sucesso=1');
    }
}
