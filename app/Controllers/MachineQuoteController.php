<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\FileUpload;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\MachineQuoteRequest;
use App\Models\User;

/** Fila de cotacoes de maquina agricola pendentes de preco (Fase 45) -- pedido chegou por audio
 *  repassado pelo usuario: /comprar ainda nao tem tabela de preco pronta por tipo de maquina, entao
 *  a solicitacao (com fotos) fica aqui ate um staff abrir, olhar as fotos e digitar o valor na mao,
 *  retornando pro cliente por fora (WhatsApp/telefone -- nao automatizado por este sistema). Tela
 *  separada de Leads/Orcamentos de proposito (pedido explicito do usuario), mesmo que o Lead de
 *  origem continue existindo no CRM normal. */
class MachineQuoteController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        $status = $_GET['status'] ?? null;

        View::render('painel/machine_quotes/index', [
            'user' => $user,
            'quotes' => MachineQuoteRequest::forScope($this->scopeUserIds($user), $status ?: null, $this->includesUnassigned($user['role_slug'])),
            'status' => $status,
            'canRespond' => in_array($user['role_slug'], ['admin', 'gerente'], true),
        ]);
    }

    public function show(string $id): void
    {
        $quote = $this->authorizeQuote((int) $id);
        $user = Auth::user();
        View::render('painel/machine_quotes/show', [
            'user' => $user,
            'quote' => $quote,
            'canRespond' => in_array($user['role_slug'], ['admin', 'gerente'], true),
        ]);
    }

    public function respond(string $id): void
    {
        $quote = $this->authorizeQuote((int) $id);
        $id = (int) $id;
        $user = Auth::user();

        // Fase 82: so Gerente e Admin inserem valor de cotacao -- pedido explicito do usuario.
        // Licenciado/Supervisor podem ver a cotacao (inclusive sem vendedor atribuido ainda) mas
        // nao decidem o preco.
        if (!in_array($user['role_slug'], ['admin', 'gerente'], true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/cotacoes-maquina/{$id}?erro=1");
        }

        $price = str_replace(',', '.', trim($_POST['quoted_price'] ?? ''));
        if (!is_numeric($price) || (float) $price <= 0) {
            Router::redirect("/painel/cotacoes-maquina/{$id}?erro=2");
        }

        MachineQuoteRequest::markResponded($id, (int) $user['id'], (float) $price, trim($_POST['internal_notes'] ?? ''));

        Router::redirect("/painel/cotacoes-maquina/{$id}?sucesso=1");
    }

    public function downloadPhoto(string $id, string $tipo): void
    {
        $quote = $this->authorizeQuote((int) $id);

        $field = match ($tipo) {
            'geral' => 'photo_general_path',
            'plaqueta' => 'photo_nameplate_path',
            'mangueira' => 'photo_hose_path',
            default => null,
        };
        if (!$field || empty($quote[$field])) {
            http_response_code(404);
            exit('Foto não encontrada.');
        }

        $path = FileUpload::path('machine_quotes', $quote[$field]);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        header('Content-Type: ' . (mime_content_type($path) ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . $tipo . '-cotacao-' . (int) $id . '.jpg"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function authorizeQuote(int $id): array
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $quote = MachineQuoteRequest::find($id);
        if (!$quote) {
            Router::redirect('/painel/cotacoes-maquina');
        }

        $scope = $this->scopeUserIds($user);
        $assignedId = (int) ($quote['assigned_user_id'] ?? 0);
        $allowed = $scope === null
            || in_array($assignedId, $scope, true)
            || ($assignedId === 0 && $this->includesUnassigned($user['role_slug']));
        if (!$allowed) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        return $quote;
    }

    /** Mesmo padrao de escopo ja usado em OrderController/LeadController -- null = sem escopo
     *  (Admin), senao a lista de ids que essa pessoa pode ver. */
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

    /** Fase 82: cotacao sem vendedor atribuido (GeoMatch nao achou ninguem no raio de 100km)
     *  precisa continuar visivel pra quem pode assumir/decidir -- Gerente, Supervisor e
     *  Licenciado, mas nao Vendedor/Gestor (pedido explicito do usuario). */
    private function includesUnassigned(string $role): bool
    {
        return in_array($role, ['gerente', 'supervisor', Roles::REGIONAL_OWNER], true);
    }
}
