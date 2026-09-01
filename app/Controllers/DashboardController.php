<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Chart;
use App\Core\DateRange;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;
use App\Models\Goal;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;

class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if (!empty($user['must_change_password'])) {
            Router::redirect('/painel/trocar-senha');
        }

        if ($user['role_slug'] === 'cliente' && !$user['email_verified_at']) {
            Router::redirect('/painel/verificar-email');
        }

        $role = $user['role_slug'];
        $data = ['user' => $user];

        if (in_array($role, Roles::STAFF, true)) {
            [$from, $to] = DateRange::fromRequest();
            [$prevFrom, $prevTo] = DateRange::previousPeriod($from, $to);

            // Vendedor ve so as proprias vendas; gestor/licenciado veem a equipe/regiao agregada;
            // supervisor/gerente veem a rede nacional que cuidam (visualizacao); admin ve tudo
            $sellerId = null;
            $sellerIds = null;
            if ($role === Roles::SELLER) {
                $sellerId = (int) $user['id'];
            } elseif ($role === 'supervisor') {
                $sellerIds = User::supervisedIds((int) $user['id']);
            } elseif ($role === 'gerente') {
                $sellerIds = User::nationalIds((int) $user['id']);
            } elseif ($role !== 'admin') {
                $sellerIds = User::downlineIds((int) $user['id']);
            }

            $current = Order::metrics($from, $to, $sellerId, $sellerIds);
            $previous = Order::metrics($prevFrom, $prevTo, $sellerId, $sellerIds);
            $cost = Order::costTotal($from, $to, $sellerId, $sellerIds);

            $data['from'] = $from;
            $data['to'] = $to;
            $data['metrics'] = $current;
            $data['changes'] = [
                'total_value' => DateRange::percentChange($current['total_value'], $previous['total_value']),
                'order_count' => DateRange::percentChange($current['order_count'], $previous['order_count']),
                'products_sold' => DateRange::percentChange($current['products_sold'], $previous['products_sold']),
                'ticket_medio' => DateRange::percentChange($current['ticket_medio'], $previous['ticket_medio']),
            ];
            $data['grossMargin'] = $current['total_value'] - $cost;
            $data['costTotal'] = $cost;
            $data['chartSvg'] = Chart::dailyLine(
                Order::dailySeries($from, $to, $sellerId, $sellerIds),
                Order::dailySeries($prevFrom, $prevTo, $sellerId, $sellerIds),
                $from,
                $to,
                $prevFrom
            );
            $data['topProducts'] = OrderItem::topProducts($from, $to, $sellerId, $sellerIds);
            $data['goals'] = array_map(
                fn ($g) => $g + ['progress' => Goal::progress($g)],
                Goal::activeFor($role === Roles::SELLER ? (int) $user['id'] : null)
            );
        }

        if ($role === 'admin') {
            $data['leadCount'] = Lead::count();
        }

        if ($role === 'cliente') {
            $client = Client::findByUserId((int) $user['id']);
            $data['myClient'] = $client;

            if ($client) {
                $orders = Order::all(['client_id' => $client['id']]);
                $data['myOrders'] = array_map(function ($o) {
                    $o['payments'] = Payment::forPayable('order', (int) $o['id']);
                    return $o;
                }, $orders);
                $data['myTotalPurchased'] = array_sum(array_map(
                    fn ($o) => $o['status'] !== 'cancelado' ? (float) $o['total_value'] : 0,
                    $orders
                ));
            }
        }

        View::render('painel/dashboard', $data);
    }
}
