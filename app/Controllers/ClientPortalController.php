<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\FileUpload;
use App\Core\Notifier;
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

        if (!Csrf::verify($_POST['csrf_token'] ?? null) || trim($_POST['description'] ?? '') === '') {
            Router::redirect("/painel/minhas-garantias/nova?order_id={$orderId}&erro=1");
        }

        try {
            $attachment = FileUpload::storeWarrantyAttachment($_FILES['attachment'] ?? []);
        } catch (\RuntimeException $e) {
            Router::redirect("/painel/minhas-garantias/nova?order_id={$orderId}&erro=2");
            return;
        }

        $warrantyId = WarrantyRequest::create($orderId, (int) $client['id'], trim($_POST['description']), $attachment);

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

        View::render('painel/client_portal/warranty_show', ['user' => Auth::user(), 'warranty' => $warranty]);
    }

    public function downloadWarrantyAttachment(string $id): void
    {
        Auth::requireRole(['cliente']);
        $client = Client::findByUserId((int) Auth::user()['id']);
        $warranty = WarrantyRequest::find((int) $id);

        if (!$client || !$warranty || (int) $warranty['client_id'] !== (int) $client['id']) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }
        if (!$warranty['attachment_path']) {
            http_response_code(404);
            exit('Anexo não encontrado.');
        }

        $path = FileUpload::path('warranties', $warranty['attachment_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        header('Content-Type: ' . (mime_content_type($path) ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($warranty['attachment_original_name'] ?: $warranty['attachment_path']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
