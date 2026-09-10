<?php

namespace App\Core;

use App\Models\Client;
use App\Models\EmailEventTemplate;
use App\Models\EmailTemplateSettings;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\QuoteItem;
use App\Models\User;
use App\Models\WhatsAppEventTemplate;

/**
 * Central de notificacoes por e-mail. Um metodo por evento -- cada um resolve os destinatarios,
 * monta o corpo a partir do template EDITAVEL do evento (App\Models\EmailEventTemplate, ver
 * eventBody()) e manda via Mailer::send(). Assunto/titulo/texto de introducao/rotulo do botao sao
 * editaveis pelo admin em /painel/configuracoes/email -- so a tabela de dados (Cliente/Valor etc)
 * e a URL do botao continuam fixas no codigo (estrutural, nao e' "texto").
 *
 * Dois niveis de destinatario, por pedido explicito do usuario:
 * - leadRoteado / orcamentoRealizado: so Vendedor responsavel + Licenciado da rede dele.
 * - pedidoRealizado / pedidoAprovado / cadastroAprovado: cadeia inteira -- Vendedor, Gestor (se
 *   houver), Licenciado, Supervisor, Gerente e todo Admin -- ja que sao os 3 eventos que o negocio
 *   inteiro (nao so a rede regional) quer acompanhar.
 * Em ambos os casos, sem duplicar e-mail se a mesma pessoa aparecer em mais de um papel (ex: lead
 * caido direto no Licenciado Central, sem Vendedor no raio).
 *
 * Alem do e-mail, os principais eventos tambem disparam WhatsApp (App\Core\EvolutionApiClient) --
 * texto diferente pro destinatario direto do evento ("self", ex: o vendedor) e pro resto da rede
 * ("network", ex: licenciado/supervisor/gerente/admin), editavel em /painel/configuracoes/whatsapp
 * (App\Models\WhatsAppEventTemplate, ver waTexts()). Alguns eventos (cancelamento, vendedor
 * inativo, licenciado pendente, lembrete de follow-up) sao WhatsApp-only, sem e-mail. Nem
 * Mailer::send nem sendWhatsApp() lancam excecao pro chamador, entao notificar nunca interrompe o
 * fluxo principal (pedido/orcamento/lead ja foi salvo antes de notificar).
 */
class Notifier
{
    private const BASE_URL = 'https://ecodiffusorebrasil.com.br';

    /** @param array $lead precisa de name/whatsapp/city */
    public static function leadRoteado(array $lead, int $assigneeId): void
    {
        $vars = ['nome' => $lead['name'] ?? '—', 'whatsapp' => $lead['whatsapp'] ?? '—', 'cidade' => $lead['city'] ?? '—'];
        $details = self::infoList(['Nome' => $vars['nome'], 'WhatsApp' => $vars['whatsapp'], 'Cidade' => $vars['cidade']]);
        $url = self::BASE_URL . '/painel/leads';
        [$subject, $title, $body] = self::eventBody('lead_roteado', $vars, $details, $url);

        [$waSelf, $waNetwork] = self::waTexts('lead_roteado', $vars + ['url' => $url]);
        self::sendToSellerAndLicenciado($assigneeId, $subject, $title, $body, $waSelf, $waNetwork);
    }

    /** @param array $quote precisa de id/seller_id/total_value/client_name */
    public static function orcamentoRealizado(array $quote): void
    {
        if (empty($quote['seller_id'])) {
            return;
        }

        [$subject, $title, $body] = self::eventBody(
            'orcamento_registrado',
            self::orderVars($quote),
            self::orderDetails($quote),
            self::BASE_URL . '/painel/orcamentos/' . (int) $quote['id']
        );

        self::sendToSellerAndLicenciado((int) $quote['seller_id'], $subject, $title, $body);
    }

    /** @param array $order precisa de id/seller_id/total_value/client_name */
    public static function pedidoRealizado(array $order): void
    {
        if (empty($order['seller_id'])) {
            return;
        }

        $vars = self::pedidoVars($order);
        $url = self::BASE_URL . '/painel/pedidos/' . (int) $order['id'];
        [$subject, $title, $body] = self::eventBody('pedido_registrado', $vars, self::pedidoDetails($vars), $url);

        [$waSelf, $waNetwork] = self::waTexts('pedido_registrado', $vars + ['url' => $url]);
        self::sendToFullChain((int) $order['seller_id'], $subject, $title, $body, $waSelf, $waNetwork);
    }

    /** @param array $order precisa de id/seller_id/total_value/client_name */
    public static function pedidoAprovado(array $order): void
    {
        if (empty($order['seller_id'])) {
            return;
        }

        $vars = self::pedidoVars($order);
        $url = self::BASE_URL . '/painel/pedidos/' . (int) $order['id'];
        [$subject, $title, $body] = self::eventBody('pedido_aprovado', $vars, self::pedidoDetails($vars), $url);

        [$waSelf, $waNetwork] = self::waTexts('pedido_aprovado', $vars + ['url' => $url]);
        self::sendToFullChain((int) $order['seller_id'], $subject, $title, $body, $waSelf, $waNetwork);
    }

    /** @param array $order precisa de id/client_name/total_value (retorno de Order::find()). So
     *  WhatsApp, pra todo usuario do papel Fabrica -- avisa que caiu um pedido novo JA PAGO pra ela
     *  incluir o codigo de rastreio (Fase 28: fabrica so ve pedidos com status verificado). Ao
     *  contrario de pedidoAprovado(), dispara mesmo sem seller_id (ex: checkout publico sem
     *  vendedor) -- a fabrica precisa saber de TODO pedido pago, tenha vendedor ou nao. */
    public static function novoPedidoPagoFabrica(array $order): void
    {
        $produtos = OrderItem::forOrder((int) $order['id']);
        $produto = $produtos
            ? implode(', ', array_map(fn ($i) => $i['product_name'] . ' (x' . (int) $i['quantity'] . ')', $produtos))
            : '—';

        $text = "📦 Novo pedido pago #{$order['id']}! Cliente {$order['client_name']}, produto: {$produto}. Acesse o painel e inclua o código de rastreio: " . self::BASE_URL . '/painel/fabrica';

        foreach (User::allByRole(Roles::FACTORY) as $fabrica) {
            if (!empty($fabrica['whatsapp'])) {
                self::sendWhatsApp($fabrica['whatsapp'], $text);
            }
        }
    }

    /** @param array $order precisa de id/seller_id/client_name (mesmas chaves de pedidoVars) --
     *  so WhatsApp, sem e-mail (nao pedido pelo usuario pra esse evento). */
    public static function pedidoCancelado(array $order): void
    {
        if (empty($order['seller_id'])) {
            return;
        }

        $orderId = (int) $order['id'];
        $vars = self::pedidoVars($order) + ['id' => (string) $orderId, 'url' => self::BASE_URL . '/painel/pedidos/' . $orderId];
        [$waSelf, $waNetwork] = self::waTexts('pedido_cancelado', $vars);

        $sellerId = (int) $order['seller_id'];
        $seller = User::find($sellerId);
        if ($seller && !empty($seller['whatsapp']) && $waSelf) {
            self::sendWhatsApp($seller['whatsapp'], $waSelf);
        }

        $licenciado = User::licenciadoFor($sellerId);
        if ($licenciado && !empty($licenciado['whatsapp']) && (int) $licenciado['id'] !== $sellerId && $waNetwork) {
            self::sendWhatsApp($licenciado['whatsapp'], $waNetwork);
        }
    }

    /** @param array $seller precisa de id/name. So WhatsApp, sem e-mail. */
    public static function vendedorInativo(array $seller, int $diasInativo): void
    {
        $vars = ['vendedor' => $seller['name'] ?? '—', 'dias' => (string) $diasInativo, 'url' => self::BASE_URL . '/painel/desempenho/vendedores'];
        [, $text] = self::waTexts('vendedor_inativo', $vars);
        if (!$text) {
            return;
        }

        $recipients = [];
        $current = User::find((int) $seller['id']);
        $licenciado = null;
        $first = true;

        for ($i = 0; $i < 10 && $current; $i++) {
            if (!$first) {
                self::addRecipient($recipients, $current, 'network');
            }
            $first = false;
            if ($current['role_slug'] === 'licenciado') {
                $licenciado = $current;
                break;
            }
            if (empty($current['manager_id'])) {
                break;
            }
            $current = User::find((int) $current['manager_id']);
        }

        self::addNetworkChain($recipients, $licenciado);

        foreach ($recipients as $r) {
            if (!empty($r['whatsapp'])) {
                self::sendWhatsApp($r['whatsapp'], $text);
            }
        }
    }

    /** @param array $licenciado precisa de id/name/city/state. So WhatsApp, sem e-mail. */
    public static function licenciadoPendenteAprovacao(array $licenciado): void
    {
        $cidade = trim(($licenciado['city'] ?? '') . (!empty($licenciado['state']) ? '/' . $licenciado['state'] : ''));
        $vars = ['nome' => $licenciado['name'] ?? '—', 'cidade' => $cidade !== '' ? $cidade : '—', 'url' => self::BASE_URL . '/painel/licenciados/aprovacoes'];
        [, $text] = self::waTexts('licenciado_pendente_aprovacao', $vars);
        if (!$text) {
            return;
        }

        $recipients = [];
        foreach (User::allByRole('admin') as $admin) {
            self::addRecipient($recipients, $admin, 'network');
        }
        foreach (User::allByRole('gerente') as $gerente) {
            self::addRecipient($recipients, $gerente, 'network');
        }

        foreach ($recipients as $r) {
            if (!empty($r['whatsapp'])) {
                self::sendWhatsApp($r['whatsapp'], $text);
            }
        }
    }

    private const PEDIDO_DETAIL_LABELS = [
        'cliente' => 'Cliente',
        'comprador_documento' => 'Documento',
        'comprador_whatsapp' => 'WhatsApp',
        'comprador_email' => 'E-mail',
        'comprador_cidade' => 'Cidade',
        'produto' => 'Produto',
        'veiculo_tipo' => 'Veículo',
        'veiculo_placa' => 'Placa',
        'pagamento_forma' => 'Pagamento',
        'pagamento_status' => 'Status do pagamento',
        'valor' => 'Valor',
        'vendedor' => 'Vendedor',
        'licenciado' => 'Licenciado',
    ];

    /** Tabela de detalhes dos e-mails de Pedido -- pedido explicito do usuario de ter produto/
     *  veiculo/comprador/pagamento/licenciado visiveis, nao so Cliente/Valor. Pula campo que veio
     *  "—" (sem dado) pra nao poluir o e-mail com linha vazia. */
    private static function pedidoDetails(array $vars): string
    {
        $pairs = [];
        foreach (self::PEDIDO_DETAIL_LABELS as $key => $label) {
            if (!empty($vars[$key]) && $vars[$key] !== '—') {
                $pairs[$label] = $vars[$key];
            }
        }
        return self::infoList($pairs);
    }

    /**
     * Resumo semanal de desempenho da equipe -- pro Licenciado/Gestor que ativou (ver
     * App\Core\WeeklyDigest, chamado do Dashboard). Nao e' um dos 5 eventos transacionais (nao
     * passa por EmailEventTemplate/eventBody()), so reaproveita o mesmo envelope visual.
     * @param array $recipient precisa de name/email
     * @param array $sellerRows linhas de Order::sellerRanking() (name/order_count/total_value)
     */
    public static function weeklyDigest(array $recipient, array $sellerRows, string $periodLabel): void
    {
        if (empty($recipient['email'])) {
            return;
        }

        $rows = [];
        foreach ($sellerRows as $r) {
            $rows[$r['name']] = (int) $r['order_count'] . ' pedido(s) — R$ ' . number_format((float) $r['total_value'], 2, ',', '.');
        }

        $body = '<p>Resumo da equipe de ' . self::esc($periodLabel) . ':</p>'
            . self::infoList($rows ?: ['Sem vendas' => 'Nenhum pedido no período'])
            . self::button(self::BASE_URL . '/painel/desempenho/vendedores', 'Ver ranking completo');

        Mailer::send($recipient['email'], 'Resumo semanal da equipe - Ecodiffusore Brasil', self::template('Resumo semanal da equipe', $body));
    }

    /** @param array $licenciado precisa de id/name/email */
    public static function cadastroAprovado(array $licenciado): void
    {
        $vars = ['nome' => $licenciado['name'] ?? '—'];
        $url = self::BASE_URL . '/painel';
        [$subject, $title, $body] = self::eventBody('cadastro_aprovado', $vars, '', $url);

        [$waSelf, $waNetwork] = self::waTexts('cadastro_aprovado', $vars + ['url' => $url]);
        self::sendToNetworkChain((int) $licenciado['id'], $subject, $title, $body, $waSelf, $waNetwork);
    }

    /** @param array $client precisa de id/name/email/whatsapp. Dispara e-mail (direto, fora do
     *  EmailEventTemplate -- mensagem transacional presa a uma senha exata, sem sentido ter
     *  intro editavel) + WhatsApp (editavel, ver WhatsAppEventTemplate) com as credenciais do
     *  portal recem-criado. Unico metodo desta classe que manda pro PROPRIO cliente (todo o resto
     *  e' pra staff) -- decisao explicita do usuario: a conta so nasce quando ele de fato compra
     *  (Pedido registrado pelo vendedor), sem cadastro previo, e ele precisa ficar sabendo. */
    public static function acessoPortalCriado(array $client, string $tempPassword): void
    {
        $url = self::BASE_URL . '/painel/login';
        $vars = [
            'nome' => $client['name'] ?? '—',
            'email' => $client['email'] ?? '—',
            'senha' => $tempPassword,
            'url' => $url,
        ];

        if (!empty($client['email'])) {
            $body = '<p>' . self::esc('Olá ' . $vars['nome'] . ', sua conta no portal Ecodiffusore Brasil foi criada.') . '</p>'
                . self::infoList(['Login' => $vars['email'], 'Senha temporária' => $tempPassword])
                . '<p>Você pode trocar a senha depois do primeiro acesso.</p>'
                . self::button($url, 'Acessar meu painel');

            Mailer::send($client['email'], 'Acesso ao seu painel - Ecodiffusore Brasil', self::template('Bem-vindo(a) ao seu painel', $body));
        }

        if (!empty($client['whatsapp'])) {
            [$text] = self::waTexts('acesso_portal_criado', $vars);
            if ($text) {
                self::sendWhatsApp($client['whatsapp'], $text);
            }
        }
    }

    /** @param array $client precisa de name/email/whatsapp. @param array $payment precisa de
     *  method/amount/due_date/checkout_url/pix_payload/produtos (retorno de Payment::create()/find()
     *  + produtos montado pelo chamador via OrderItem::forOrder()/QuoteItem::forQuote()). Dispara
     *  e-mail + WhatsApp direto pro cliente com o link/QR de pagamento -- gerado assim que a
     *  cobranca e' criada (App\Controllers\PaymentController::generateCharge()), tanto pra Pedido
     *  quanto pra Orcamento. */
    public static function cobrancaGerada(array $client, array $payment, string $payableType, int $payableId): void
    {
        $label = $payableType === 'quote' ? 'Orçamento' : 'Pedido';
        $installments = (int) ($payment['installments'] ?? 1);
        $formaLabel = self::PAYMENT_METHOD_LABELS[$payment['method']] ?? $payment['method'];
        if ($installments > 1) {
            $parcelaValue = 'R$ ' . number_format(((float) $payment['amount']) / $installments, 2, ',', '.');
            $formaLabel .= " — {$installments}x de {$parcelaValue}";
        }

        $vars = [
            'pedido' => "{$label} #{$payableId}",
            'produto' => $payment['produtos'] ?? '—',
            'valor' => 'R$ ' . number_format((float) $payment['amount'], 2, ',', '.'),
            'forma' => $formaLabel,
            'vencimento' => date('d/m/Y', strtotime($payment['due_date'])),
            'url' => $payment['checkout_url'] ?? self::BASE_URL,
        ];

        if (!empty($client['email'])) {
            $body = '<p>' . self::esc("Sua cobrança do {$label} #{$payableId} foi gerada.") . '</p>'
                . self::infoList(array_filter([
                    'Produto' => $payment['produtos'] ?? null,
                    'Valor' => $vars['valor'],
                    'Forma' => $vars['forma'],
                    'Vencimento' => $vars['vencimento'],
                ]))
                . self::button($vars['url'], 'Pagar agora');
            if (!empty($payment['pix_payload'])) {
                $body .= '<p>Pix copia-e-cola:</p><p style="word-break:break-all; font-size:12px; color:#666;">' . self::esc($payment['pix_payload']) . '</p>';
            }

            Mailer::send($client['email'], "Cobrança do {$label} #{$payableId} - Ecodiffusore Brasil", self::template('Sua cobrança foi gerada', $body));
        }

        if (!empty($client['whatsapp'])) {
            [$text] = self::waTexts('cobranca_gerada', $vars);
            if ($text) {
                if (!empty($payment['pix_payload'])) {
                    $text .= "\n\nPix copia-e-cola:\n" . $payment['pix_payload'];
                }
                self::sendWhatsApp($client['whatsapp'], $text);
            }
        }
    }

    /** @param array $order precisa de id/client_whatsapp/tracking_carrier/tracking_code/
     *  prazo_entrega (retorno de Order::find()). So WhatsApp, pro PROPRIO cliente (mesma excecao
     *  de acessoPortalCriado) -- disparado pela fabrica quando atualiza a entrega (Fase 28). */
    public static function pedidoAtualizacaoEntrega(array $order): void
    {
        if (empty($order['client_whatsapp'])) {
            return;
        }

        $vars = [
            'transportadora' => $order['tracking_carrier'] ?: '—',
            'codigo' => $order['tracking_code'] ?: '—',
            'prazo' => !empty($order['prazo_entrega']) ? date('d/m/Y', strtotime($order['prazo_entrega'])) : 'a definir',
            'url' => self::BASE_URL . '/painel/meus-pedidos/' . (int) $order['id'],
        ];
        [$text] = self::waTexts('pedido_atualizacao_entrega', $vars);
        if ($text) {
            self::sendWhatsApp($order['client_whatsapp'], $text);
        }
    }

    /** @param array $quote precisa de id/client_name/client_whatsapp/lead_whatsapp/seller_id/
     *  total_value (retorno de Quote::find()). So WhatsApp, pro PROPRIO lead/cliente -- lembrete
     *  calmo de orcamento parado sem resposta (App\Core\QuoteLeadReminder, no maximo 2 disparos por
     *  orcamento, pra nao virar spam). Prefere client_whatsapp; cai pro lead_whatsapp se o cliente
     *  nao tiver telefone cadastrado (ex: cliente criado so com nome+documento). */
    public static function orcamentoLembreteLead(array $quote): void
    {
        $whatsapp = $quote['client_whatsapp'] ?? $quote['lead_whatsapp'] ?? null;
        if (empty($whatsapp)) {
            return;
        }

        $items = QuoteItem::forQuote((int) $quote['id']);
        $produtos = $items
            ? implode(', ', array_map(fn ($i) => $i['product_name'], $items))
            : 'Ecodiffusore';

        $seller = !empty($quote['seller_id']) ? User::find((int) $quote['seller_id']) : null;
        $sellerName = $seller['name'] ?? 'a Ecodiffusore';
        $sellerWhatsapp = $seller['whatsapp'] ?? null;
        $sellerDigits = $sellerWhatsapp ? preg_replace('/\D/', '', $sellerWhatsapp) : null;
        $url = $sellerDigits ? 'https://wa.me/55' . $sellerDigits : self::BASE_URL;

        $vars = [
            'nome' => $quote['client_name'] ?? '—',
            'produto' => $produtos,
            'valor' => number_format((float) $quote['total_value'], 2, ',', '.'),
            'vendedor' => $sellerName,
            'url' => $url,
        ];

        [$text] = self::waTexts('orcamento_lembrete_lead', $vars);
        if ($text) {
            self::sendWhatsApp($whatsapp, $text);
        }
    }

    /** @param array $order precisa de id/verified_at/client_name/client_whatsapp/client_email
     *  (retorno de Order::pendingExtendedWarrantyReminders()). $tier: 1=dia1, 2=dia5, 3=dia10,
     *  4=dia15. Cobranca automatica (WhatsApp + e-mail) pro cliente que JA PAGOU mas ainda NAO
     *  abriu a Garantia Estendida (90 dias, prazo de 15 dias pra pedir) -- tom escalando em
     *  urgencia, pedido explicito do usuario. Nao muda em nada o fluxo de abrir garantia em si
     *  (WarrantyRequest) -- so' cobra quem ainda nao pediu. */
    public static function garantiaEstendidaLembrete(array $order, int $tier): void
    {
        $diasRestantes = match ($tier) {
            1 => 14,
            2 => 10,
            3 => 5,
            default => 0,
        };
        $eventKey = match ($tier) {
            1 => 'garantia_estendida_lembrete_dia1',
            2 => 'garantia_estendida_lembrete_dia5',
            3 => 'garantia_estendida_lembrete_dia10',
            default => 'garantia_estendida_lembrete_dia15',
        };

        $orderId = (int) $order['id'];
        $url = self::BASE_URL . '/painel/minhas-garantias/nova?order_id=' . $orderId;
        $vars = [
            'cliente' => $order['client_name'] ?? '—',
            'pedido' => (string) $orderId,
            'dias_restantes' => (string) $diasRestantes,
            'url' => $url,
        ];

        if (!empty($order['client_whatsapp'])) {
            [$text] = self::waTexts($eventKey, $vars);
            if ($text) {
                self::sendWhatsApp($order['client_whatsapp'], $text);
            }
        }

        if (!empty($order['client_email'])) {
            [$subject, $bodyHtml] = match ($tier) {
                1 => ['Você já pode solicitar sua Garantia Estendida', 'Seu pedido #' . $orderId . ' foi confirmado! Você tem direito à <strong>Garantia Estendida</strong> do seu Ecodiffusore — são só 15 dias pra solicitar, então não deixa pra depois.'],
                2 => ['Faltam 10 dias pra solicitar sua Garantia Estendida', 'Passando pra lembrar: restam <strong>10 dias</strong> pra você solicitar a Garantia Estendida do pedido #' . $orderId . '. É rápido, leva só alguns minutos.'],
                3 => ['⏰ Faltam só 5 dias — Garantia Estendida', 'Atenção: faltam apenas <strong>5 dias</strong> pra solicitar a Garantia Estendida do seu pedido #' . $orderId . '. Depois desse prazo não será mais possível pedir.'],
                default => ['🚨 Último dia pra solicitar sua Garantia Estendida', 'Hoje é o <strong>último dia</strong> pra solicitar a Garantia Estendida do seu pedido #' . $orderId . '. Não perca essa proteção extra — solicite agora.'],
            };

            $html = '<p>Olá, ' . self::esc((string) ($order['client_name'] ?? '')) . '!</p>'
                . '<p>' . $bodyHtml . '</p>'
                . self::button($url, 'Solicitar Garantia Estendida');

            Mailer::send($order['client_email'], $subject . ' - Ecodiffusore Brasil', self::template($subject, $html));
        }
    }

    /** @param array $newUser precisa de name/whatsapp. So WhatsApp, direto pro Gestor/Vendedor
     *  recem-cadastrado -- pede pra ele confirmar que esta de acordo com as condicoes de comissao
     *  que o Licenciado configurou (link publico com token, ver AcceptanceController). */
    public static function propostaComissaoCriada(array $newUser, string $token): void
    {
        if (empty($newUser['whatsapp'])) {
            return;
        }

        $url = self::BASE_URL . '/aceite-comissao/' . $token;
        $text = "Olá, {$newUser['name']}! Você foi cadastrado(a) no painel da Ecodiffusore Brasil. Confira as condições de comissão combinadas e confirme que está de acordo, é rápido: {$url}";
        self::sendWhatsApp($newUser['whatsapp'], $text);
    }

    /** @param array $warranty precisa de id/order_id/seller_id/client_name (retorno de
     *  WarrantyRequest::find()). So WhatsApp, sem e-mail -- notifica quem vendeu o pedido, nao o
     *  cliente (mensagens pro cliente ficam pra fase seguinte). Notifica TAMBEM Admin/Gerente
     *  (quem realmente aprova, Roles::SUPERVISOR_ASSIGNMENT) -- pedido explicito do usuario:
     *  quanto antes aprovar, mais chance do Termo de Garantia ja sair junto com o pedido pela
     *  fabrica, entao quem aprova precisa saber na hora, nao so quando abrir o menu por conta
     *  propria (ate agora so o vendedor/licenciado da venda eram avisados). */
    public static function garantiaSolicitada(array $warranty): void
    {
        if (empty($warranty['seller_id'])) {
            return;
        }

        $sellerId = (int) $warranty['seller_id'];
        $vars = [
            'cliente' => $warranty['client_name'] ?? '—',
            'id' => (string) $warranty['order_id'],
            'vendedor' => User::find($sellerId)['name'] ?? '—',
            'url' => self::BASE_URL . '/painel/garantias/' . (int) $warranty['id'],
        ];
        [$waSelf, $waNetwork] = self::waTexts('garantia_solicitada', $vars);

        $seller = User::find($sellerId);
        if ($seller && !empty($seller['whatsapp']) && $waSelf) {
            self::sendWhatsApp($seller['whatsapp'], $waSelf);
        }

        $licenciado = User::licenciadoFor($sellerId);
        if ($licenciado && !empty($licenciado['whatsapp']) && (int) $licenciado['id'] !== $sellerId && $waNetwork) {
            self::sendWhatsApp($licenciado['whatsapp'], $waNetwork);
        }

        $urgentText = "⚠️ Nova solicitação de Termo de Garantia aguardando aprovação -- cliente {$vars['cliente']}, pedido #{$vars['id']}. Quanto antes aprovar, mais rápido a fábrica recebe o Termo pra despachar junto. Analise aqui: {$vars['url']}";
        foreach (array_merge(User::allByRole('admin'), User::allByRole('gerente')) as $approver) {
            if (!empty($approver['whatsapp'])) {
                self::sendWhatsApp($approver['whatsapp'], $urgentText);
            }
        }
    }

    /** @param array $seller precisa de whatsapp. @param array<int,string> $leadNames. So WhatsApp. */
    public static function followUpLembrete(array $seller, array $leadNames): void
    {
        if (empty($seller['whatsapp'])) {
            return;
        }

        $vars = ['leads' => implode(', ', $leadNames), 'url' => self::BASE_URL . '/painel/leads'];
        [$text] = self::waTexts('follow_up_lembrete', $vars);
        if ($text) {
            self::sendWhatsApp($seller['whatsapp'], $text);
        }
    }

    /** @param array $row precisa de client_name/total_value */
    private static function orderVars(array $row): array
    {
        return [
            'cliente' => $row['client_name'] ?? '—',
            'valor' => 'R$ ' . number_format((float) ($row['total_value'] ?? 0), 2, ',', '.'),
        ];
    }

    private const PAYMENT_METHOD_LABELS = ['PIX' => 'Pix', 'BOLETO' => 'Boleto', 'CREDIT_CARD' => 'Cartão'];
    private const PAYMENT_STATUS_LABELS = ['pendente' => 'Pendente', 'pago' => 'Pago', 'vencido' => 'Vencido', 'cancelado' => 'Cancelado', 'reembolsado' => 'Reembolsado'];

    /**
     * Variaveis completas pros e-mails de Pedido (registrado/aprovado) -- pedido explicito do
     * usuario de ter dados de produto/veiculo/comprador/pagamento/licenciado, nao so cliente/
     * valor. Order::find() ja traz client_name/client_whatsapp/client_city/client_state/
     * seller_name via JOIN, mas produto (order_items), pagamento (payments) e documento/e-mail do
     * comprador (clients) precisam de consulta a parte -- so acontece pra quem realmente vai
     * receber e-mail (nao no preview, que usa dados de exemplo fixos, ver previewHtml()).
     * @param array $order precisa de id/client_id/seller_id/client_name/client_whatsapp/
     *                      client_city/client_state/vehicle_type/vehicle_plate/total_value
     */
    private static function pedidoVars(array $order): array
    {
        $client = !empty($order['client_id']) ? Client::find((int) $order['client_id']) : null;

        $items = OrderItem::forOrder((int) $order['id']);
        $produtos = $items
            ? implode(', ', array_map(fn ($i) => $i['product_name'] . ' (x' . (int) $i['quantity'] . ')', $items))
            : '—';

        $lastPayment = Payment::forPayable('order', (int) $order['id'])[0] ?? null;

        $licenciado = !empty($order['seller_id']) ? User::licenciadoFor((int) $order['seller_id']) : null;

        $cidade = trim(($order['client_city'] ?? '') . (!empty($order['client_state']) ? '/' . $order['client_state'] : ''));

        return [
            'cliente' => $order['client_name'] ?? '—',
            'valor' => 'R$ ' . number_format((float) ($order['total_value'] ?? 0), 2, ',', '.'),
            'comprador_documento' => $client['document'] ?? '—',
            'comprador_email' => $client['email'] ?? '—',
            'comprador_whatsapp' => $order['client_whatsapp'] ?? $client['whatsapp'] ?? '—',
            'comprador_cidade' => $cidade !== '' ? $cidade : '—',
            'produto' => $produtos,
            'veiculo_placa' => $order['vehicle_plate'] ?? '—',
            'veiculo_tipo' => $order['vehicle_type'] ?? '—',
            'pagamento_forma' => $lastPayment ? (self::PAYMENT_METHOD_LABELS[$lastPayment['method']] ?? $lastPayment['method']) : '—',
            'pagamento_status' => $lastPayment ? (self::PAYMENT_STATUS_LABELS[$lastPayment['status']] ?? $lastPayment['status']) : '—',
            'licenciado' => $licenciado['name'] ?? '—',
            'vendedor' => $order['seller_name'] ?? '—',
        ];
    }

    /** Mesmas chaves de pedidoVars(), com dados de mentirinha -- usado so no preview
     *  (App\Controllers\EmailTemplateSettingsController), pra nunca expor dado de comprador real
     *  numa tela de configuracao. */
    private static function samplePedidoVars(): array
    {
        return [
            'cliente' => 'Cliente Exemplo',
            'valor' => 'R$ 2.836,00',
            'comprador_documento' => '123.456.789-00',
            'comprador_email' => 'cliente@exemplo.com',
            'comprador_whatsapp' => '(45) 99999-0000',
            'comprador_cidade' => 'Toledo/PR',
            'produto' => 'Linha Scania (até 2018) (x1)',
            'veiculo_placa' => 'ABC-1234',
            'veiculo_tipo' => 'Caminhão',
            'pagamento_forma' => 'Pix',
            'pagamento_status' => 'Pago',
            'licenciado' => 'Licenciado Exemplo',
            'vendedor' => 'Vendedor Exemplo',
        ];
    }

    /**
     * Monta [subject, title, bodyHtml] a partir do template editavel do evento (subject/title/
     * intro_text/button_label, ver App\Models\EmailEventTemplate) + $detailsHtml (tabela de dados,
     * sempre gerada no codigo) + o botao (URL fixa, rotulo editavel). $vars interpola {placeholder}
     * dentro de intro_text -- ver interpolate().
     * @param array<string,string> $vars
     */
    private static function eventBody(string $eventKey, array $vars, string $detailsHtml, string $buttonUrl): array
    {
        $tpl = EmailEventTemplate::find($eventKey);

        $body = '<p>' . self::interpolate($tpl['intro_text'], $vars) . '</p>'
            . $detailsHtml
            . self::button($buttonUrl, $tpl['button_label']);

        return [$tpl['subject'], $tpl['title'], $body];
    }

    /** Substitui {chave} pelo valor correspondente em $vars -- template e valores sao escapados
     *  ANTES da substituicao (nao depois), pra nao arriscar um {placeholder} virar HTML por
     *  coincidencia de caracteres. Chave de $vars nao encontrada no texto e' simplesmente ignorada. */
    private static function interpolate(string $template, array $vars): string
    {
        $escaped = self::esc($template);
        foreach ($vars as $key => $value) {
            $escaped = str_replace('{' . $key . '}', self::esc((string) $value), $escaped);
        }
        return $escaped;
    }

    /** Busca os textos editaveis (WhatsAppEventTemplate, /painel/configuracoes/whatsapp) do
     *  evento e interpola {vars} -- sem escapar HTML, mensagem de WhatsApp e texto puro. Qualquer
     *  um dos dois pode vir null se o evento nao usa aquela variante (ver SELF_ONLY/NETWORK_ONLY)
     *  ou se o template ainda nao tiver linha (defensivo, a migracao ja semeia todas).
     *  @return array{0:?string,1:?string} [textoSelf, textoNetwork] */
    private static function waTexts(string $eventKey, array $vars): array
    {
        $tpl = WhatsAppEventTemplate::find($eventKey);
        $textSelf = $tpl['text_self'] ?? null;
        $textNetwork = $tpl['text_network'] ?? null;

        return [
            $textSelf !== null && $textSelf !== '' ? self::waInterpolate($textSelf, $vars) : null,
            $textNetwork !== null && $textNetwork !== '' ? self::waInterpolate($textNetwork, $vars) : null,
        ];
    }

    /** Substitui {chave} pelo valor em $vars, sem escapar (texto puro de WhatsApp, nao HTML). */
    private static function waInterpolate(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }
        return $template;
    }

    /** Dispara uma mensagem de WhatsApp via Evolution API -- nunca lanca excecao pro chamador
     *  (mesmo espirito do Mailer::send, ver docblock da classe), so loga se falhar. */
    private static function sendWhatsApp(string $number, string $text): void
    {
        try {
            (new EvolutionApiClient())->sendText($number, $text);
        } catch (\Throwable $e) {
            error_log('WhatsApp dispatch falhou: ' . $e->getMessage());
        }
    }

    /** @param array $row precisa de client_name/total_value */
    private static function orderDetails(array $row): string
    {
        return self::infoList([
            'Cliente' => $row['client_name'] ?? '—',
            'Valor' => 'R$ ' . number_format((float) ($row['total_value'] ?? 0), 2, ',', '.'),
        ]);
    }

    /** Vendedor responsavel + Licenciado da rede dele -- ver docblock da classe. $waSelf vai pro
     *  vendedor, $waNetwork pro licenciado (null = nao manda WhatsApp pra aquele papel). */
    private static function sendToSellerAndLicenciado(int $sellerId, string $subject, string $title, string $body, ?string $waSelf = null, ?string $waNetwork = null): void
    {
        $seller = User::find($sellerId);
        if (!$seller) {
            return;
        }

        $recipients = [];
        self::addRecipient($recipients, $seller, 'self');

        $licenciado = User::licenciadoFor($sellerId);
        if ($licenciado) {
            self::addRecipient($recipients, $licenciado, 'network');
        }

        self::dispatch($recipients, $subject, $title, $body, $waSelf, $waNetwork);
    }

    /** Vendedor, Gestor (se houver), Licenciado, Supervisor, Gerente e todo Admin -- ver docblock
     *  da classe. Caminha a cadeia de manager_id a partir do vendedor ate achar o Licenciado
     *  (mesmo criterio de parada de User::licenciadoFor(), so que aqui tambem guarda cada nivel
     *  intermediario -- licenciadoFor() so devolve o Licenciado final). $waSelf vai so pro
     *  vendedor (primeiro da cadeia), $waNetwork pro resto (gestor/licenciado/supervisor/gerente/
     *  admin). */
    private static function sendToFullChain(int $sellerId, string $subject, string $title, string $body, ?string $waSelf = null, ?string $waNetwork = null): void
    {
        $recipients = [];
        $current = User::find($sellerId);
        $licenciado = null;
        $first = true;

        for ($i = 0; $i < 10 && $current; $i++) {
            self::addRecipient($recipients, $current, $first ? 'self' : 'network');
            $first = false;
            if ($current['role_slug'] === 'licenciado') {
                $licenciado = $current;
                break;
            }
            if (empty($current['manager_id'])) {
                break;
            }
            $current = User::find((int) $current['manager_id']);
        }

        self::addNetworkChain($recipients, $licenciado);
        self::dispatch($recipients, $subject, $title, $body, $waSelf, $waNetwork);
    }

    /** Licenciado + Supervisor dele + Gerente do Supervisor + todo Admin -- usado tanto por
     *  sendToFullChain() (a partir de um Vendedor) quanto direto por cadastroAprovado(). $waSelf
     *  vai pro licenciado, $waNetwork pro resto (supervisor/gerente/admin). */
    private static function sendToNetworkChain(int $licenciadoId, string $subject, string $title, string $body, ?string $waSelf = null, ?string $waNetwork = null): void
    {
        $recipients = [];
        $licenciado = User::find($licenciadoId);
        if ($licenciado) {
            self::addRecipient($recipients, $licenciado, 'self');
        }

        self::addNetworkChain($recipients, $licenciado);
        self::dispatch($recipients, $subject, $title, $body, $waSelf, $waNetwork);
    }

    private static function addNetworkChain(array &$recipients, ?array $licenciado): void
    {
        if ($licenciado && !empty($licenciado['supervisor_id'])) {
            $supervisor = User::find((int) $licenciado['supervisor_id']);
            if ($supervisor) {
                self::addRecipient($recipients, $supervisor, 'network');
                if (!empty($supervisor['manager_id'])) {
                    $gerente = User::find((int) $supervisor['manager_id']);
                    if ($gerente) {
                        self::addRecipient($recipients, $gerente, 'network');
                    }
                }
            }
        }

        foreach (User::allByRole('admin') as $admin) {
            self::addRecipient($recipients, $admin, 'network');
        }
    }

    /** @param array<int,array{email:?string,whatsapp:?string,bucket:string}> $recipients */
    private static function addRecipient(array &$recipients, array $user, string $bucket): void
    {
        $id = (int) $user['id'];
        if (!isset($recipients[$id])) {
            $recipients[$id] = ['email' => $user['email'] ?? null, 'whatsapp' => $user['whatsapp'] ?? null, 'bucket' => $bucket];
        }
    }

    /** @param array<int,array{email:?string,whatsapp:?string,bucket:string}> $recipients ja sem duplicata */
    private static function dispatch(array $recipients, string $subject, string $title, string $body, ?string $waSelf = null, ?string $waNetwork = null): void
    {
        $html = self::template($title, $body);
        foreach ($recipients as $r) {
            if (!empty($r['email'])) {
                Mailer::send($r['email'], $subject . ' - Ecodiffusore Brasil', $html);
            }
            $waText = $r['bucket'] === 'self' ? $waSelf : $waNetwork;
            if ($waText && !empty($r['whatsapp'])) {
                self::sendWhatsApp($r['whatsapp'], $waText);
            }
        }
    }

    private static function infoList(array $pairs): string
    {
        $items = '';
        foreach ($pairs as $label => $value) {
            $items .= '<tr>'
                . '<td style="padding:4px 12px 4px 0; color:#6b6f76; font-size:13px; white-space:nowrap;">' . self::esc((string) $label) . '</td>'
                . '<td style="padding:4px 0; color:#1a1a1a; font-size:13px; font-weight:600;">' . self::esc((string) $value) . '</td>'
                . '</tr>';
        }
        return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px 0 20px;">' . $items . '</table>';
    }

    private static function button(string $url, string $label): string
    {
        $accent = EmailTemplateSettings::current()['accent_color'];

        return '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="border-radius:8px; background:' . self::esc($accent) . ';">'
            . '<a href="' . $url . '" style="display:inline-block; padding:11px 22px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:8px;">' . self::esc($label) . '</a>'
            . '</td></tr></table>';
    }

    /**
     * Preview do template pra tela de configuracoes (App\Controllers\EmailTemplateSettingsController)
     * -- monta um evento com dados de exemplo, usando o template ATUAL (editado ou padrao) desse
     * evento, sem mandar e-mail nenhum de verdade. $eventKey precisa ser uma das
     * EmailEventTemplate::KEYS -- cai em 'pedido_registrado' se vier vazio/invalido.
     */
    public static function previewHtml(string $eventKey = 'pedido_registrado'): string
    {
        if (!in_array($eventKey, EmailEventTemplate::KEYS, true)) {
            $eventKey = 'pedido_registrado';
        }

        $sampleOrder = ['id' => 1, 'client_name' => 'Cliente Exemplo', 'total_value' => 2836.0];

        [$subject, $title, $body] = match ($eventKey) {
            'lead_roteado' => self::eventBody(
                'lead_roteado',
                ['nome' => 'Cliente Exemplo', 'whatsapp' => '(45) 99999-0000', 'cidade' => 'Toledo/PR'],
                self::infoList(['Nome' => 'Cliente Exemplo', 'WhatsApp' => '(45) 99999-0000', 'Cidade' => 'Toledo/PR']),
                self::BASE_URL . '/painel/leads'
            ),
            'orcamento_registrado' => self::eventBody('orcamento_registrado', self::orderVars($sampleOrder), self::orderDetails($sampleOrder), self::BASE_URL . '/painel/orcamentos/1'),
            'pedido_aprovado' => self::eventBody('pedido_aprovado', self::samplePedidoVars(), self::pedidoDetails(self::samplePedidoVars()), self::BASE_URL . '/painel/pedidos/1'),
            'cadastro_aprovado' => self::eventBody('cadastro_aprovado', ['nome' => 'Licenciado Exemplo'], '', self::BASE_URL . '/painel'),
            default => self::eventBody('pedido_registrado', self::samplePedidoVars(), self::pedidoDetails(self::samplePedidoVars()), self::BASE_URL . '/painel/pedidos/1'),
        };

        return self::template($title, $body);
    }

    /**
     * Envelope padrao de todos os e-mails: cabecalho com logo, corpo branco, rodape discreto --
     * visual configuravel em /painel/configuracoes/email (App\Models\EmailTemplateSettings),
     * pedido explicito do usuario pra nao depender de deploy de codigo pra ajustar isso. Tabelas +
     * estilo inline (nao <style>) -- e-mail HTML precisa disso pra renderizar igual em qualquer
     * cliente (Gmail, Outlook etc.).
     */
    private static function template(string $title, string $bodyHtml): string
    {
        $settings = EmailTemplateSettings::current();

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f0e8; padding:32px 16px; font-family:Arial,Helvetica,sans-serif;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background:#ffffff; border-radius:14px; overflow:hidden;">'
            . '<tr><td style="background:' . self::esc($settings['header_bg']) . '; padding:22px 32px;" align="left">'
            . '<img src="' . self::esc($settings['logo_url']) . '" alt="Ecodiffusore Brasil" height="28" style="display:block; border:0;">'
            . '</td></tr>'
            . '<tr><td style="padding:32px 32px 28px;">'
            . '<h1 style="margin:0 0 16px; font-size:18px; font-weight:600; color:#1a1a1a;">' . self::esc($title) . '</h1>'
            . '<div style="font-size:14px; line-height:1.6; color:#333333;">' . $bodyHtml . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:16px 32px; background:#f7f7f5; border-top:1px solid #ececec;">'
            . '<p style="margin:0; font-size:12px; color:#8a8a8a;">' . self::esc($settings['footer_text']) . '</p>'
            . '</td></tr>'
            . '</table>'
            . '</td></tr>'
            . '</table>';
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
