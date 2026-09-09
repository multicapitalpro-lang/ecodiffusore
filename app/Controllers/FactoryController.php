<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\CorreiosClient;
use App\Core\Notifier;
use App\Core\Pdf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\CompanySettings;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WarrantyRequest;

/** Acesso restrito da fabrica terceirizada -- so pedidos pagos (verificado), so pra atualizar
 *  rastreio/previsao de entrega. Nunca ve preco/comissao/vendedor (Fase 28). */
class FactoryController
{
    public function index(): void
    {
        Auth::requireRole([Roles::FACTORY]);
        $orders = Order::forFactory();
        $orderIds = array_map(fn ($o) => (int) $o['id'], $orders);

        $termByOrderId = WarrantyRequest::approvedTermByOrderIds($orderIds);
        $itemsByOrderId = OrderItem::forOrders($orderIds);
        foreach ($orders as &$o) {
            $o['warranty_term_id'] = $termByOrderId[(int) $o['id']] ?? null;
            $o['items'] = $itemsByOrderId[(int) $o['id']] ?? [];
            // Fila da fabrica costuma ser curta (so pedidos pagos ainda nao entregues) -- 1 consulta
            // extra por pedido aqui e' aceitavel, mesmo padrao ja usado noutras telas admin pequenas.
            $o['vehicle_info'] = Order::vehicleInfoFor((int) $o['id']);
        }
        unset($o);

        View::render('painel/factory/index', [
            'user' => Auth::user(),
            'orders' => $orders,
            'stats' => Order::factoryStats(),
        ]);
    }

    /** Termo de Garantia do pedido, se ja existir uma garantia aprovada/concluida pra ele --
     *  mesmo PDF que o staff/cliente baixam (WarrantyController::downloadTerm()), so que aqui o
     *  acesso e' pelo numero do PEDIDO (nao da garantia) e restrito ao papel Fabrica, que nao tem
     *  acesso a /painel/garantias. */
    public function downloadWarrantyTerm(string $id): void
    {
        Auth::requireRole([Roles::FACTORY]);
        $orderId = (int) $id;

        $approved = WarrantyRequest::approvedTermByOrderIds([$orderId]);
        $warrantyId = $approved[$orderId] ?? null;
        if (!$warrantyId) {
            http_response_code(404);
            exit('Termo de garantia não disponível para este pedido.');
        }

        $warranty = WarrantyRequest::find($warrantyId);

        ob_start();
        View::render('painel/warranties/term_pdf', [
            'warranty' => $warranty,
            'items' => OrderItem::forOrder($orderId),
            'company' => CompanySettings::current(),
            'vehicle' => Order::vehicleInfoFor($orderId),
        ], null);
        $html = ob_get_clean();

        Pdf::download($html, 'termo-garantia-pedido-' . $orderId . '.pdf', 'portrait');
    }

    public function updateDelivery(string $id): void
    {
        Auth::requireRole([Roles::FACTORY]);
        $id = (int) $id;

        $order = Order::find($id);
        if (!$order || $order['status'] !== 'verificado') {
            Router::redirect('/painel/fabrica?erro=1');
        }
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/fabrica?erro=1');
        }

        $trackingCode = trim($_POST['tracking_code'] ?? '');
        Order::updateTracking($id, $trackingCode, trim($_POST['tracking_carrier'] ?? ''), trim($_POST['prazo_entrega'] ?? ''));

        // Consulta o status real na hora que a fabrica cadastra o codigo -- feedback imediato pra
        // ela mesma na tela; o CorreiosTrackingChecker::processDue() cuida de manter atualizado
        // depois disso (o pacote continua se movendo, o status de hoje fica velho em poucas horas).
        if ($trackingCode !== '') {
            try {
                $result = (new CorreiosClient())->track($trackingCode);
                if ($result) {
                    Order::updateTrackingStatus($id, $result['status'] ?? null, $result['date'] ?? null, $result['entregue'] ?? false);
                }
            } catch (\Throwable $e) {
                // Best-effort -- sem status na hora nao deve impedir o salvamento do rastreio.
            }
        }

        $updated = Order::find($id);
        if ($updated) {
            Notifier::pedidoAtualizacaoEntrega($updated);
        }

        Router::redirect('/painel/fabrica?sucesso=1');
    }

    public function markDelivered(string $id): void
    {
        Auth::requireRole([Roles::FACTORY]);
        $id = (int) $id;

        $order = Order::find($id);
        if (!$order || $order['status'] !== 'verificado') {
            Router::redirect('/painel/fabrica?erro=1');
        }
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/fabrica?erro=1');
        }

        Order::markDelivered($id);

        Router::redirect('/painel/fabrica?sucesso=1');
    }
}
