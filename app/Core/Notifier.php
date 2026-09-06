<?php

namespace App\Core;

use App\Models\Client;
use App\Models\EmailEventTemplate;
use App\Models\EmailTemplateSettings;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;

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
 * So e-mail por enquanto -- estrutura pensada pra, no futuro, cada metodo tambem despachar
 * WhatsApp (Evolution API) pros mesmos destinatarios, sem mudar os pontos de chamada no resto do
 * app. mail() (usado por Mailer::send) nunca lanca excecao, entao chamar estes metodos nunca
 * interrompe o fluxo principal (pedido/orcamento/lead ja foi salvo antes de notificar).
 */
class Notifier
{
    private const BASE_URL = 'https://ecodiffusorebrasil.com.br';

    /** @param array $lead precisa de name/whatsapp/city */
    public static function leadRoteado(array $lead, int $assigneeId): void
    {
        $vars = ['nome' => $lead['name'] ?? '—', 'whatsapp' => $lead['whatsapp'] ?? '—', 'cidade' => $lead['city'] ?? '—'];
        $details = self::infoList(['Nome' => $vars['nome'], 'WhatsApp' => $vars['whatsapp'], 'Cidade' => $vars['cidade']]);
        [$subject, $title, $body] = self::eventBody('lead_roteado', $vars, $details, self::BASE_URL . '/painel/leads');

        self::sendToSellerAndLicenciado($assigneeId, $subject, $title, $body);
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

        [$subject, $title, $body] = self::eventBody(
            'pedido_registrado',
            self::pedidoVars($order),
            self::orderDetails($order),
            self::BASE_URL . '/painel/pedidos/' . (int) $order['id']
        );

        self::sendToFullChain((int) $order['seller_id'], $subject, $title, $body);
    }

    /** @param array $order precisa de id/seller_id/total_value/client_name */
    public static function pedidoAprovado(array $order): void
    {
        if (empty($order['seller_id'])) {
            return;
        }

        [$subject, $title, $body] = self::eventBody(
            'pedido_aprovado',
            self::pedidoVars($order),
            self::orderDetails($order),
            self::BASE_URL . '/painel/pedidos/' . (int) $order['id']
        );

        self::sendToFullChain((int) $order['seller_id'], $subject, $title, $body);
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
        [$subject, $title, $body] = self::eventBody(
            'cadastro_aprovado',
            ['nome' => $licenciado['name'] ?? '—'],
            '',
            self::BASE_URL . '/painel'
        );

        self::sendToNetworkChain((int) $licenciado['id'], $subject, $title, $body);
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

    /** @param array $row precisa de client_name/total_value */
    private static function orderDetails(array $row): string
    {
        return self::infoList([
            'Cliente' => $row['client_name'] ?? '—',
            'Valor' => 'R$ ' . number_format((float) ($row['total_value'] ?? 0), 2, ',', '.'),
        ]);
    }

    /** Vendedor responsavel + Licenciado da rede dele -- ver docblock da classe. */
    private static function sendToSellerAndLicenciado(int $sellerId, string $subject, string $title, string $body): void
    {
        $seller = User::find($sellerId);
        if (!$seller) {
            return;
        }

        $recipients = [];
        if (!empty($seller['email'])) {
            $recipients[(int) $seller['id']] = $seller['email'];
        }

        $licenciado = User::licenciadoFor($sellerId);
        if ($licenciado && !empty($licenciado['email'])) {
            $recipients[(int) $licenciado['id']] = $licenciado['email'];
        }

        self::dispatch($recipients, $subject, $title, $body);
    }

    /** Vendedor, Gestor (se houver), Licenciado, Supervisor, Gerente e todo Admin -- ver docblock
     *  da classe. Caminha a cadeia de manager_id a partir do vendedor ate achar o Licenciado
     *  (mesmo criterio de parada de User::licenciadoFor(), so que aqui tambem guarda cada nivel
     *  intermediario -- licenciadoFor() so devolve o Licenciado final). */
    private static function sendToFullChain(int $sellerId, string $subject, string $title, string $body): void
    {
        $recipients = [];
        $current = User::find($sellerId);
        $licenciado = null;

        for ($i = 0; $i < 10 && $current; $i++) {
            if (!empty($current['email'])) {
                $recipients[(int) $current['id']] = $current['email'];
            }
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
        self::dispatch($recipients, $subject, $title, $body);
    }

    /** Licenciado + Supervisor dele + Gerente do Supervisor + todo Admin -- usado tanto por
     *  sendToFullChain() (a partir de um Vendedor) quanto direto por cadastroAprovado(). */
    private static function sendToNetworkChain(int $licenciadoId, string $subject, string $title, string $body): void
    {
        $recipients = [];
        $licenciado = User::find($licenciadoId);
        if ($licenciado && !empty($licenciado['email'])) {
            $recipients[(int) $licenciado['id']] = $licenciado['email'];
        }

        self::addNetworkChain($recipients, $licenciado);
        self::dispatch($recipients, $subject, $title, $body);
    }

    private static function addNetworkChain(array &$recipients, ?array $licenciado): void
    {
        if ($licenciado && !empty($licenciado['supervisor_id'])) {
            $supervisor = User::find((int) $licenciado['supervisor_id']);
            if ($supervisor) {
                if (!empty($supervisor['email'])) {
                    $recipients[(int) $supervisor['id']] = $supervisor['email'];
                }
                if (!empty($supervisor['manager_id'])) {
                    $gerente = User::find((int) $supervisor['manager_id']);
                    if ($gerente && !empty($gerente['email'])) {
                        $recipients[(int) $gerente['id']] = $gerente['email'];
                    }
                }
            }
        }

        foreach (User::allByRole('admin') as $admin) {
            if (!empty($admin['email'])) {
                $recipients[(int) $admin['id']] = $admin['email'];
            }
        }
    }

    /** @param array<int,string> $recipients id => email, ja sem duplicata */
    private static function dispatch(array $recipients, string $subject, string $title, string $body): void
    {
        $html = self::template($title, $body);
        foreach ($recipients as $email) {
            Mailer::send($email, $subject . ' - Ecodiffusore Brasil', $html);
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
            'pedido_aprovado' => self::eventBody('pedido_aprovado', self::samplePedidoVars(), self::orderDetails($sampleOrder), self::BASE_URL . '/painel/pedidos/1'),
            'cadastro_aprovado' => self::eventBody('cadastro_aprovado', ['nome' => 'Licenciado Exemplo'], '', self::BASE_URL . '/painel'),
            default => self::eventBody('pedido_registrado', self::samplePedidoVars(), self::orderDetails($sampleOrder), self::BASE_URL . '/painel/pedidos/1'),
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
