<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Chart;
use App\Core\DateRange;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;
use App\Models\Goal;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;

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

        if (in_array($role, ['admin', 'gerente', 'supervisor', 'licenciado'], true)) {
            [$from, $to] = DateRange::fromRequest();
            [$prevFrom, $prevTo] = DateRange::previousPeriod($from, $to);

            $sellerId = $role === 'licenciado' ? (int) $user['id'] : null;

            $current = Order::metrics($from, $to, $sellerId);
            $previous = Order::metrics($prevFrom, $prevTo, $sellerId);
            $cost = Order::costTotal($from, $to, $sellerId);

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
                Order::dailySeries($from, $to, $sellerId),
                Order::dailySeries($prevFrom, $prevTo, $sellerId),
                $from,
                $to,
                $prevFrom
            );
            $data['topProducts'] = OrderItem::topProducts($from, $to, $sellerId);
            $data['goals'] = array_map(
                fn ($g) => $g + ['progress' => Goal::progress($g)],
                Goal::activeFor($role === 'licenciado' ? (int) $user['id'] : null)
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
