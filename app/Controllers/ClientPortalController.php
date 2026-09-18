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
use App\Models\CompanySettings;
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

        $approvedWarranty = null;
        foreach (WarrantyRequest::forOrder($id) as $w) {
            if (in_array($w['status'], ['aprovada', 'concluida'], true)) {
                $approvedWarranty = $w;
                break;
            }
        }

        View::render('painel/client_portal/order', [
            'user' => $user,
            'order' => $order,
            'items' => OrderItem::forOrder($id),
            'payments' => Payment::forPayable('order', $id),
            'approvedWarranty' => $approvedWarranty,
        ]);
    }

    /** Fase 60/61: CNH, documento do veiculo, placa, 3 fotos e telemetria deixaram de ser
     *  exigidos ANTES da venda (pedido explicito do usuario, "facilitando a compra") -- agora o
     *  proprio cliente envia tudo aqui, no painel dele, depois de já ter comprado. Sem o cadastro
     *  completo (Order::REQUIRED_VEHICLE_FIELDS) o pedido pago fica de fora de Order::forFactory()
     *  (nunca é enviado pra fábrica) até completar -- ver Order::hasRequiredDocuments(). */
    public function uploadOrderDocuments(string $id): void
    {
        Auth::requireRole(['cliente']);
        $client = Client::findByUserId((int) Auth::user()['id']);
        $id = (int) $id;
        $order = $client ? Order::find($id) : null;

        if (!$order || (int) $order['client_id'] !== (int) $client['id']) {
            Router::redirect('/painel');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/meus-pedidos/{$id}?erro_docs=1");
        }

        $fields = [];

        $plate = strtoupper(trim($_POST['vehicle_plate'] ?? ''));
        if ($plate !== '') {
            $fields['vehicle_plate'] = $plate;
        }

        try {
            $vehicleDocument = FileUpload::storeVehicleDocument($_FILES['vehicle_document'] ?? []);
            $cnhDocument = FileUpload::storeCnhDocument($_FILES['cnh_document'] ?? []);
            $photo1 = FileUpload::storeVehiclePhoto($_FILES['photo1'] ?? []);
            $photo2 = FileUpload::storeVehiclePhoto($_FILES['photo2'] ?? []);
            $photo3 = FileUpload::storeVehiclePhoto($_FILES['photo3'] ?? []);
            $telemetry = FileUpload::storeTelemetryFile($_FILES['telemetry'] ?? []);
        } catch (\RuntimeException $e) {
            Router::redirect("/painel/meus-pedidos/{$id}?erro_docs=" . urlencode($e->getMessage()));
        }

        if ($vehicleDocument) {
            $fields['vehicle_document_path'] = $vehicleDocument['stored_name'];
        }
        if ($cnhDocument) {
            $fields['cnh_document_path'] = $cnhDocument['stored_name'];
        }
        if ($photo1) {
            $fields['photo1_path'] = $photo1['stored_name'];
        }
        if ($photo2) {
            $fields['photo2_path'] = $photo2['stored_name'];
        }
        if ($photo3) {
            $fields['photo3_path'] = $photo3['stored_name'];
        }
        if ($telemetry) {
            $fields['telemetry_path'] = $telemetry['stored_name'];
        }

        if (!$fields) {
            Router::redirect("/painel/meus-pedidos/{$id}?erro_docs=" . urlencode('Selecione pelo menos um arquivo ou preencha a placa.'));
        }

        Order::updateDocuments($id, $fields);

        $updatedOrder = Order::find($id);
        if ($updatedOrder && Order::hasRequiredDocuments($updatedOrder)) {
            Notifier::pedidoDocumentosEnviados($updatedOrder);
        }

        Router::redirect("/painel/meus-pedidos/{$id}?docs_sucesso=1");
    }

    public function warranties(): void
    {
        Auth::requireRole(['cliente']);
        $client = Client::findByUserId((int) Auth::user()['id']);

        $eligibleOrders = [];
        if ($client) {
            $eligibleOrders = array_values(array_filter(
                Order::all(['client_id' => $client['id']]),
                fn ($o) => $o['status'] === 'verificado'
            ));
        }

        View::render('painel/client_portal/warranties', [
            'user' => Auth::user(),
            'warranties' => $client ? WarrantyRequest::forClient((int) $client['id']) : [],
            'eligibleOrders' => $eligibleOrders,
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

        $driverName = trim($_POST['driver_name'] ?? '');
        $driverDocument = trim($_POST['driver_document'] ?? '');
        if ($driverName === '' || $driverDocument === '') {
            Router::redirect("/painel/minhas-garantias/nova?order_id={$orderId}&erro=4");
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

        $warrantyId = WarrantyRequest::create($orderId, (int) $client['id'], $driverName, $driverDocument);
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

    /** Comprovante de Pós-venda de Instalação em PDF -- so disponivel depois de confirmada. Mesmo
     *  conteudo que WarrantyController::downloadTerm() gera pro staff. */
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
            'company' => CompanySettings::current(),
            'vehicle' => Order::vehicleInfoFor((int) $warranty['order_id']),
        ], null);
        $html = ob_get_clean();

        Pdf::download($html, 'termo-garantia-pedido-' . (int) $warranty['order_id'] . '.pdf', 'portrait');
    }
}
