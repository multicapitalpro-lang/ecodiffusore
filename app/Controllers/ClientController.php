<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Csv;
use App\Core\FileUpload;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;

class ClientController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $clients = Client::all($this->scopeFilters($user));

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'seller_id' => $_GET['seller_id'] ?? '',
            'status' => $_GET['status'] ?? '',
            'city' => trim($_GET['city'] ?? ''),
        ];

        [$clients, $stats] = $this->attachPurchaseStatus($clients);

        $showLicenciadoColumn = in_array($user['role_slug'], ['supervisor', 'gerente'], true);
        if ($showLicenciadoColumn) {
            foreach ($clients as &$c) {
                $c['licenciado_name'] = User::licenciadoNameFor((int) ($c['seller_id'] ?? 0));
            }
            unset($c);
        }

        $filtered = array_values(array_filter($clients, function ($c) use ($filters) {
            if ($filters['q'] !== '' && stripos($c['name'] . ' ' . $c['email'] . ' ' . $c['document'], $filters['q']) === false) {
                return false;
            }
            if ($filters['seller_id'] !== '' && (string) ($c['seller_id'] ?? '') !== (string) $filters['seller_id']) {
                return false;
            }
            if ($filters['status'] !== '' && $c['status'] !== $filters['status']) {
                return false;
            }
            if ($filters['city'] !== '' && stripos((string) $c['city'], $filters['city']) === false) {
                return false;
            }
            return true;
        }));

        View::render('painel/clients/index', [
            'user' => $user,
            'clients' => $filtered,
            'stats' => $stats,
            'filters' => $filters,
            'sellers' => $this->sellerOptions($user),
            'showLicenciadoColumn' => $showLicenciadoColumn,
        ]);
    }

    /** Anexa o resumo de compras (Order::purchaseSummaryByClientIds) em cada cliente e ja soma
     * as contagens pros cards do topo -- "status relacionado a compra": nunca comprou, tem
     * pedido em aberto, ja pagou algum pedido. */
    private function attachPurchaseStatus(array $clients): array
    {
        $ids = array_map(fn ($c) => (int) $c['id'], $clients);
        $summaries = Order::purchaseSummaryByClientIds($ids);

        $stats = ['total' => count($clients), 'pagos' => 0, 'abertos' => 0, 'nunca_compraram' => 0];

        foreach ($clients as &$client) {
            $summary = $summaries[(int) $client['id']] ?? null;
            $orderCount = (int) ($summary['order_count'] ?? 0);
            $openCount = (int) ($summary['open_count'] ?? 0);
            $paidCount = (int) ($summary['paid_count'] ?? 0);

            if ($orderCount === 0) {
                $purchaseStatus = ['slug' => 'nunca_comprou', 'label' => 'Nunca comprou', 'badge' => 'inactive'];
                $stats['nunca_compraram']++;
            } elseif ($openCount > 0) {
                $purchaseStatus = ['slug' => 'em_aberto', 'label' => 'Pedido em aberto', 'badge' => 'novo'];
                $stats['abertos']++;
            } elseif ($paidCount > 0) {
                $purchaseStatus = ['slug' => 'pago', 'label' => 'Já pagou', 'badge' => 'active'];
                $stats['pagos']++;
            } else {
                $purchaseStatus = ['slug' => 'sem_pagamento', 'label' => 'Comprou, sem pagamento confirmado', 'badge' => 'novo'];
            }

            $client['purchase_status'] = $purchaseStatus;
            $client['order_count'] = $orderCount;
        }
        unset($client);

        return [$clients, $stats];
    }

    public function create(): void
    {
        Auth::requireRole(Roles::STAFF);
        Router::redirect('/painel/clientes?novo=1');
    }

    public function export(): void
    {
        Auth::requireRole(Roles::STAFF);

        $rows = array_map(fn ($c) => [
            $c['id'],
            $c['name'],
            $c['document'] ?: '',
            $c['person_type'] === 'juridica' ? 'Jurídica' : 'Física',
            $c['email'] ?: '',
            $c['whatsapp'] ?: '',
            $c['city'] ?: '',
            $c['state'] ?: '',
            $c['seller_name'] ?: '',
            $c['status'] === 'ativo' ? 'Ativo' : 'Inativo',
        ], Client::all($this->scopeFilters(Auth::user())));

        Csv::download('clientes.csv', ['ID', 'Nome', 'Documento', 'Tipo', 'E-mail', 'WhatsApp', 'Cidade', 'UF', 'Vendedor', 'Status'], $rows);
    }

    public function store(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['name' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect('/painel/clientes?erro=1');
        }

        $errors = $this->validate($_POST);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            View::render('painel/clients/index', [
                'user' => $user,
                'clients' => Client::all($this->scopeFilters($user)),
                'sellers' => $this->sellerOptions($user),
                'errors' => $errors,
                'values' => $_POST,
            ]);
            return;
        }

        // Vendedor so cadastra cliente pra si mesmo -- nunca aceita seller_id vindo do POST (evita
        // atribuir o cliente novo a outro vendedor da mesma equipe via campo escondido/editado).
        $data = $_POST;
        if ($user['role_slug'] === Roles::SELLER) {
            $data['seller_id'] = $user['id'];
        }

        $clientId = Client::create($data);
        $redirectTo = $_GET['redirect_to'] ?? null;

        if ($redirectTo && str_starts_with($redirectTo, '/painel/')) {
            $sep = str_contains($redirectTo, '?') ? '&' : '?';
            $target = $redirectTo . $sep . 'novo=1&cliente_id=' . $clientId;
        } else {
            $target = '/painel/clientes?sucesso=1';
        }

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'id' => $clientId, 'redirect' => $target]);
        }

        Router::redirect($target);
    }

    public function show(string $id): void
    {
        $client = $this->authorizeClient((int) $id);
        $id = (int) $id;

        View::render('painel/clients/show', [
            'user' => Auth::user(),
            'client' => $client,
            'orders' => Order::all(['client_id' => $id]),
            'transactions' => FinancialTransaction::all(['client_id' => $id]),
            'notes' => ClientNote::forClient($id),
        ]);
    }

    public function createAccess(string $id): void
    {
        $client = $this->authorizeClient((int) $id);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/clientes/{$id}?erro=1");
        }

        if (!empty($client['user_id'])) {
            Router::redirect("/painel/clientes/{$id}");
        }
        if (empty($client['email']) || !filter_var($client['email'], FILTER_VALIDATE_EMAIL)) {
            Router::redirect("/painel/clientes/{$id}?erro_acesso=1");
        }
        if (User::emailExists($client['email'])) {
            Router::redirect("/painel/clientes/{$id}?erro_acesso=2");
        }

        $tempPassword = Client::autoCreatePortalAccess($client);

        Router::redirect("/painel/clientes/{$id}?acesso_criado=1&temp=" . urlencode($tempPassword));
    }

    public function storeNote(string $id): void
    {
        $this->authorizeClient((int) $id);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null) || trim($_POST['note'] ?? '') === '') {
            Router::redirect("/painel/clientes/{$id}");
        }

        $followUpDate = trim($_POST['follow_up_date'] ?? '');
        if ($followUpDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $followUpDate)) {
            $followUpDate = '';
        }

        $attachment = null;
        try {
            $attachment = FileUpload::storeClientNoteAttachment($_FILES['attachment'] ?? []);
        } catch (\RuntimeException $e) {
            Router::redirect("/painel/clientes/{$id}?erro_anexo=1#notas");
        }

        ClientNote::create($id, (int) Auth::user()['id'], trim($_POST['note']), $followUpDate !== '' ? $followUpDate : null, $attachment);

        Router::redirect("/painel/clientes/{$id}#notas");
    }

    public function downloadNoteAttachment(string $id): void
    {
        Auth::requireRole(Roles::STAFF);

        $note = ClientNote::find((int) $id);
        if (!$note || !$note['attachment_path']) {
            http_response_code(404);
            exit('Anexo não encontrado.');
        }

        $this->authorizeClient((int) $note['client_id']);

        $path = FileUpload::path('client_notes', $note['attachment_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        header('Content-Type: ' . (mime_content_type($path) ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($note['attachment_original_name'] ?: $note['attachment_path']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function edit(string $id): void
    {
        $client = $this->authorizeClient((int) $id);
        $user = Auth::user();

        $isFragment = isset($_GET['fragment']);

        View::render('painel/clients/form', [
            'user' => $user,
            'editing' => $client,
            'sellers' => $this->sellerOptions($user),
            'errors' => [],
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function update(string $id): void
    {
        $user = Auth::user();
        $this->authorizeClient((int) $id);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['name' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect("/painel/clientes/{$id}/editar?erro=1");
        }

        $errors = $this->validate($_POST, $id);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            View::render('painel/clients/form', [
                'user' => $user,
                'editing' => array_merge(['id' => $id], $_POST),
                'sellers' => $this->sellerOptions($user),
                'errors' => $errors,
            ]);
            return;
        }

        // Mesma trava do store(): vendedor nao consegue reatribuir o proprio cliente pra outro
        // vendedor via POST direto (o campo do form nem deveria oferecer essa opcao, mas o
        // controller e' a barreira que realmente vale).
        $data = $_POST;
        if ($user['role_slug'] === Roles::SELLER) {
            $data['seller_id'] = $user['id'];
        }

        Client::update($id, $data);

        $target = '/painel/clientes?sucesso=1';
        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }

        Router::redirect($target);
    }

    public function destroy(string $id): void
    {
        $this->authorizeClient((int) $id);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/clientes?erro=csrf');
        }

        try {
            Client::delete($id);
        } catch (\PDOException $e) {
            Router::redirect('/painel/clientes?erro=vinculo');
        }

        Router::redirect('/painel/clientes?sucesso=2');
    }

    public function destroyBulk(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/clientes?erro=csrf');
        }

        $ids = array_unique(array_map('intval', $_POST['ids'] ?? []));
        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            // Mesmo escopo de acesso individual (canAccessSeller) -- evita excluir em lote um
            // cliente fora da propria rede via POST direto (a tela so lista/marca clientes do
            // proprio escopo, mas o controller e' a barreira real).
            $client = Client::find($id);
            if (!$client || !$this->canAccessSeller($user, (int) ($client['seller_id'] ?? 0))) {
                $failed++;
                continue;
            }
            try {
                Client::delete($id);
                $deleted++;
            } catch (\PDOException $e) {
                $failed++;
            }
        }

        Router::redirect("/painel/clientes?sucesso=3&deletados={$deleted}&falhas={$failed}");
    }

    public function bulkAssignSeller(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/clientes?erro=1');
        }

        $sellerId = !empty($_POST['seller_id']) ? (int) $_POST['seller_id'] : null;

        // Trava dupla: so reatribui cliente que ja esta no escopo de quem esta agindo (evita
        // sequestrar cliente de outra rede via POST direto), e so pra um vendedor que tambem esta
        // no proprio escopo (evita "doar" cliente pra vendedor de outro licenciado).
        if ($sellerId !== null && !in_array($sellerId, array_column($this->sellerOptions($user), 'id'), true)) {
            Router::redirect('/painel/clientes?erro=1');
        }

        $clientIds = array_unique(array_map('intval', $_POST['client_ids'] ?? []));
        $allowedIds = [];
        foreach ($clientIds as $id) {
            $client = Client::find($id);
            if ($client && $this->canAccessSeller($user, (int) ($client['seller_id'] ?? 0))) {
                $allowedIds[] = $id;
            }
        }

        if ($allowedIds) {
            Client::bulkAssignSeller($allowedIds, $sellerId);
        }

        Router::redirect('/painel/clientes?sucesso=1');
    }

    private function validate(array $input, ?int $excludeId = null): array
    {
        $errors = [];

        if (trim($input['name'] ?? '') === '') {
            $errors['name'] = 'Informe o nome do cliente.';
        }

        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        }

        // Nao pode ter o mesmo CPF/CNPJ ou WhatsApp em dois cadastros diferentes -- documento e
        // telefone identificam a pessoa de forma confiavel, nome e' subjetivo demais (pedido
        // explicito do usuario: "nao podemos ter o mesmo lead em dois CRMs diferentes").
        $duplicate = Client::findDuplicate($input['document'] ?? null, $input['whatsapp'] ?? null, $excludeId);
        if ($duplicate) {
            $field = !empty($input['document']) && preg_replace('/\D/', '', $input['document']) === preg_replace('/\D/', '', (string) $duplicate['document'])
                ? 'document'
                : 'whatsapp';
            $errors[$field] = 'Já existe um cliente cadastrado com esse ' . ($field === 'document' ? 'CPF/CNPJ' : 'WhatsApp')
                . ' (' . $duplicate['name'] . ($duplicate['seller_name'] ? ', vendedor ' . $duplicate['seller_name'] : '') . ').';
        }

        return $errors;
    }

    /** Verifica que o cliente {id} existe e esta no escopo de quem esta logado -- barreira real
     *  contra acesso direto por URL a um cliente de outro vendedor/rede (index/export ja escopam a
     *  listagem, mas sem isso um vendedor ainda conseguiria abrir /painel/clientes/{id} de qualquer
     *  cliente digitando o id na URL). Mesmo padrao de OrderController::authorizeOrder. */
    private function authorizeClient(int $id): array
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $client = Client::find($id);
        if (!$client) {
            Router::redirect('/painel/clientes');
        }

        if (!$this->canAccessSeller($user, (int) ($client['seller_id'] ?? 0))) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        return $client;
    }

    /** Filtro de escopo pra listar clientes: vendedor so os proprios (estrito, sem orfaos --
     *  cliente sem vendedor nunca foi dele), gestor/licenciado a regiao (+ orfaos, pra poder
     *  assumir/distribuir), supervisor/gerente a rede que cuidam, admin tudo. Mesmo padrao ja
     *  usado em OrderController/QuoteController/LeadController. */
    private function scopeFilters(array $user): array
    {
        if ($user['role_slug'] === 'admin') {
            return [];
        }
        if ($user['role_slug'] === Roles::SELLER) {
            return ['seller_id' => $user['id']];
        }
        if ($user['role_slug'] === 'supervisor') {
            return ['seller_ids' => User::supervisedIds((int) $user['id'])];
        }
        if ($user['role_slug'] === 'gerente') {
            return ['seller_ids' => User::nationalIds((int) $user['id'])];
        }

        return ['seller_ids' => User::downlineIds((int) $user['id']), 'include_unassigned' => true];
    }

    /** Cliente sem vendedor (seller_id null) so e' acessivel por quem gerencia (fallback abaixo),
     *  nunca por um vendedor comum -- mesma logica de "orfao" do scopeFilters(). */
    private function canAccessSeller(array $user, int $sellerId): bool
    {
        if ($user['role_slug'] === 'admin') {
            return true;
        }
        if ($user['role_slug'] === Roles::SELLER) {
            return $sellerId === (int) $user['id'];
        }
        if ($user['role_slug'] === 'supervisor') {
            return in_array($sellerId, User::supervisedIds((int) $user['id']), true);
        }
        if ($user['role_slug'] === 'gerente') {
            return in_array($sellerId, User::nationalIds((int) $user['id']), true);
        }

        return $sellerId === 0 || in_array($sellerId, User::downlineIds((int) $user['id']), true);
    }

    /** Vendedores disponiveis pro dropdown "Vendedor vinculado" no form de cliente, escopado por
     *  regiao -- mesmo padrao de OrderController::sellerOptions. */
    private function sellerOptions(array $user): array
    {
        $sellers = User::allByRole(Roles::SELLER);
        if ($user['role_slug'] === 'admin') {
            return $sellers;
        }

        $downline = User::downlineIds((int) $user['id']);
        return array_values(array_filter($sellers, fn ($s) => in_array((int) $s['id'], $downline, true)));
    }
}
