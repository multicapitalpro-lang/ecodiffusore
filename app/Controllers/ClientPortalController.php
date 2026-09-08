<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\FileUpload;
use App\Core\Notifier;
use App\Core\Pdf;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\WarrantyRequest;

class ClientPortalController
{
    public function showOrder(string $id): void
    {
        Auth::requireRole(['cliente']);
        $user = Auth::user();
        $id = (int) $id;

        $client = Client::findByUserId((int) $user['id']);
        $order = $client ? Order::find($id) : null;

        if (!$order || (int) $order['client_id'] !== (int) $client['id']) {
            Router::redirect('/painel');
        }

        View::render('painel/client_portal/order', [
            'user' => $user,
            'order' => $order,
            'items' => OrderItem::forOrder($id),
            'payments' => Payment::forPayable('order', $id),
        ]);
    }

    public function warranties(): void
    {
        Auth::requireRole(['cliente']);
        $client = Client::findByUserId((int) Auth::user()['id']);

        View::render('painel/client_portal/warranties', [
            'user' => Auth::user(),
            'warranties' => $client ? WarrantyRequest::forClient((int) $client['id']) : [],
        ]);
    }

    public function newWarranty(): void
    {
        Auth::requireRole(['cliente']);
        $client = Client::findByUserId((int) Auth::user()['id']);
        $orderId = (int) ($_GET['order_id'] ?? 0);
        $order = $orderId ? Order::find($orderId) : null;

        if (!$client || !$order || (int) $order['client_id'] !== (int) $client['id']) {
            Router::redirect('/painel/minhas-garantias');
        }

        View::render('painel/client_portal/warranty_form', ['user' => Auth::user(), 'order' => $order, 'errors' => []]);
    }

    public function storeWarranty(): void
    {
        Auth::requireRole(['cliente']);
        $client = Client::findByUserId((int) Auth::user()['id']);
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $order = $orderId ? Order::find($orderId) : null;

        if (!$client || !$order || (int) $order['client_id'] !== (int) $client['id']) {
            Router::redirect('/painel/minhas-garantias');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/minhas-garantias/nova?order_id={$orderId}&erro=1");
        }

        // CNH + documento do veiculo + 3 fotos, todos obrigatorios (pedido explicito do usuario,
        // Fase 27c) -- pra dar suporte de verdade a uma solicitacao de garantia.
        $required = ['cnh' => 'cnh', 'documento_veiculo' => 'documento_veiculo', 'foto1' => 'foto', 'foto2' => 'foto', 'foto3' => 'foto', 'telemetria' => 'telemetria'];
        foreach (array_keys($required) as $field) {
            if (empty($_FILES[$field]['name'])) {
                Router::redirect("/painel/minhas-garantias/nova?order_id={$orderId}&erro=3");
            }
        }

        $stored = [];
        try {
            foreach ($required as $field => $type) {
                $stored[] = ['type' => $type, 'file' => FileUpload::storeWarrantyAttachment($_FILES[$field])];
            }
        } catch (\RuntimeException $e) {
            Router::redirect("/painel/minhas-garantias/nova?order_id={$orderId}&erro=2");
            return;
        }

        $warrantyId = WarrantyRequest::create($orderId, (int) $client['id'], trim($_POST['description'] ?? ''));
        foreach ($stored as $item) {
            WarrantyRequest::addAttachment($warrantyId, $item['type'], $item['file']['stored_name'], $item['file']['original_name']);
        }

        $warranty = WarrantyRequest::find($warrantyId);
        if ($warranty) {
            Notifier::garantiaSolicitada($warranty);
        }

        Router::redirect('/painel/minhas-garantias?sucesso=1');
    }

    public function showWarranty(string $id): void
    {
        Auth::requireRole(['cliente']);
        $client = Client::findByUserId((int) Auth::user()['id']);
        $warranty = WarrantyRequest::find((int) $id);

        if (!$client || !$warranty || (int) $warranty['client_id'] !== (int) $client['id']) {
            Router::redirect('/painel/minhas-garantias');
        }

        View::render('painel/client_portal/warranty_show', [
            'user' => Auth::user(),
            'warranty' => $warranty,
            'attachments' => WarrantyRequest::attachmentsFor((int) $warranty['id']),
        ]);
    }

    public function downloadWarrantyAttachment(string $id, string $attachmentId): void
    {
        Auth::requireRole(['cliente']);
        $client = Client::findByUserId((int) Auth::user()['id']);
        $warranty = WarrantyRequest::find((int) $id);

        if (!$client || !$warranty || (int) $warranty['client_id'] !== (int) $client['id']) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        $attachment = WarrantyRequest::findAttachment((int) $attachmentId);
        if (!$attachment || (int) $attachment['warranty_request_id'] !== (int) $warranty['id']) {
            http_response_code(404);
            exit('Anexo não encontrado.');
        }

        $path = FileUpload::path('warranties', $attachment['stored_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        header('Content-Type: ' . (mime_content_type($path) ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($attachment['original_name'] ?: $attachment['stored_path']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Termo de Garantia em PDF -- so disponivel depois de aprovada. Mesmo conteudo que
     *  WarrantyController::downloadTerm() gera pro staff. */
    public function downloadWarrantyTerm(string $id): void
    {
        Auth::requireRole(['cliente']);
        $client = Client::findByUserId((int) Auth::user()['id']);
        $warranty = WarrantyRequest::find((int) $id);

        if (!$client || !$warranty || (int) $warranty['client_id'] !== (int) $client['id']) {
            Router::redirect('/painel/minhas-garantias');
        }
        if (!in_array($warranty['status'], ['aprovada', 'concluida'], true)) {
            Router::redirect("/painel/minhas-garantias/{$id}");
        }

        ob_start();
        View::render('painel/warranties/term_pdf', [
            'warranty' => $warranty,
            'items' => OrderItem::forOrder((int) $warranty['order_id']),
        ], null);
        $html = ob_get_clean();

        Pdf::download($html, 'termo-garantia-pedido-' . (int) $warranty['order_id'] . '.pdf', 'portrait');
    }
}
