<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\CorreiosClient;
use App\Core\FileUpload;
use App\Core\Notifier;
use App\Core\Pdf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Core\Config;
use App\Models\CompanySettings;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
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

    /** Fase 61: CNH/documento do veiculo/fotos/telemetria enviados pelo cliente -- a fabrica
     *  precisa ver pra montar a peca certa. So libera pedido JA' pago (mesma checagem de
     *  updateDelivery()/markDelivered()) -- Fabrica fica fora da hierarquia de vendedor/
     *  licenciado, entao nao reaproveita OrderController::authorizeOrder(). */
    public function downloadDocument(string $id, string $field): void
    {
        Auth::requireRole([Roles::FACTORY]);
        $id = (int) $id;

        $order = Order::find($id);
        if (!$order || $order['status'] !== 'verificado' || !array_key_exists($field, Order::VEHICLE_FILE_SUBDIRS) || empty($order[$field])) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        $path = FileUpload::path(Order::VEHICLE_FILE_SUBDIRS[$field], $order[$field]);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        $extension = pathinfo($order[$field], PATHINFO_EXTENSION);
        $mimeType = mime_content_type($path) ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $field . '-pedido-' . $id . ($extension ? '.' . $extension : '') . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Fase 101: comprovante do Pix que o Admin mandou pra Fabrica num pedido a preco de custo --
     *  a fabrica precisa disso pra confirmar que recebeu o pagamento antes de despachar. Mesmo
     *  padrao de downloadDocument(), so' que a pasta/campo sao os do comprovante de pagamento. */
    public function downloadFactoryPaymentProof(string $id): void
    {
        Auth::requireRole([Roles::FACTORY]);
        $id = (int) $id;

        $order = Order::find($id);
        if (!$order || empty($order['is_cost_price']) || empty($order['factory_payment_proof_path'])) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        $path = FileUpload::path('factory_payment_proofs', $order['factory_payment_proof_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        header('Content-Type: ' . (mime_content_type($path) ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . rawurlencode($order['factory_payment_proof_original_name'] ?: 'comprovante.pdf') . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Fase 71: "Consulta de Pedidos" -- pedido explicito do usuario, pra fabrica conseguir
     *  responder um cliente que liga perguntando do pedido dele (nome, modelo(s), cidade, prazo
     *  de entrega vendido), mesmo fora da fila de despacho (Order::forFactory(), so' pedidos
     *  pagos com documento completo). Nunca mostra valor/comissao/vendedor. */
    public function consultaPedidos(): void
    {
        Auth::requireRole([Roles::FACTORY]);

        View::render('painel/factory/consulta_pedidos', [
            'user' => Auth::user(),
            'orders' => Order::forFactoryOverview(),
        ]);
    }

    /** Fase 71: visao passiva do funil de Leads/interessados -- so' nome, cidade e etapa (nunca
     *  telefone/e-mail), pedido explicito do usuario pra a fabrica acompanhar volume sem poder
     *  negociar direto com quem aparece ali (ver Lead::forFactoryOverview()). */
    public function leads(): void
    {
        Auth::requireRole([Roles::FACTORY]);

        $stageNames = [];
        foreach (LeadStage::all() as $stage) {
            $stageNames[$stage['slug']] = $stage['name'];
        }

        View::render('painel/factory/leads', [
            'user' => Auth::user(),
            'leads' => Lead::forFactoryOverview(),
            'stageNames' => $stageNames,
        ]);
    }

    /** Consulta (Fase 39): a Fabrica confere se quem apareceu direto com ela (fora do fluxo normal
     *  de venda) ja e' Licenciado/Vendedor/Gestor ou Lead nosso, e -- se nao for -- tem um link fixo
     *  pra mandar a pessoa comprar com a Ecodiffusore Brasil (rede) em vez de negociar por fora.
     *  Sem escopo de hierarquia de proposito: a Fabrica precisa poder achar QUALQUER pessoa da rede
     *  inteira, nao so de uma regiao. Nunca mostra preco/comissao/email -- so nome + CPF/telefone,
     *  o minimo pra identificar. */
    public function rede(): void
    {
        Auth::requireRole([Roles::FACTORY]);
        $term = trim($_GET['q'] ?? '');

        $licenciados = [];
        $vendedores = [];
        $leads = [];
        if (mb_strlen($term) >= 2) {
            $licenciados = User::searchByRoles(['licenciado'], $term);
            $vendedores = User::searchByRoles(['gestor', 'vendedor'], $term);
            $leads = Lead::search($term);
        }

        View::render('painel/factory/rede', [
            'user' => Auth::user(),
            'term' => $term,
            'licenciados' => $licenciados,
            'vendedores' => $vendedores,
            'leads' => $leads,
            'purchaseLink' => rtrim(Config::get('app_url'), '/') . '/comprar',
        ]);
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
