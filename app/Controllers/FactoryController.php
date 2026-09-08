<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Notifier;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Order;

/** Acesso restrito da fabrica terceirizada -- so pedidos pagos (verificado), so pra atualizar
 *  rastreio/previsao de entrega. Nunca ve preco/comissao/vendedor (Fase 28). */
class FactoryController
{
    public function index(): void
    {
        Auth::requireRole([Roles::FACTORY]);
        View::render('painel/factory/index', ['user' => Auth::user(), 'orders' => Order::forFactory()]);
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

        Order::updateTracking($id, trim($_POST['tracking_code'] ?? ''), trim($_POST['tracking_carrier'] ?? ''), trim($_POST['prazo_entrega'] ?? ''));

        $updated = Order::find($id);
        if ($updated) {
            Notifier::pedidoAtualizacaoEntrega($updated);
        }

        Router::redirect('/painel/fabrica?sucesso=1');
    }
}
