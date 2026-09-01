<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;

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
}
