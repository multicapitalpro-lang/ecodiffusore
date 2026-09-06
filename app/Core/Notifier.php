<?php

namespace App\Core;

use App\Models\EmailTemplateSettings;
use App\Models\User;

/**
 * Central de notificacoes por e-mail. Um metodo por evento -- cada um resolve os destinatarios e
 * monta o corpo (ja envolvido no template padrao, ver template()) e manda via Mailer::send().
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
        $body = '<p>Um novo lead foi direcionado pra você:</p>'
            . self::infoList([
                'Nome' => $lead['name'] ?? '—',
                'WhatsApp' => $lead['whatsapp'] ?? '—',
                'Cidade' => $lead['city'] ?? '—',
            ])
            . self::button(self::BASE_URL . '/painel/leads', 'Acessar Leads no painel');

        self::sendToSellerAndLicenciado($assigneeId, 'Novo lead direcionado a você', 'Novo lead direcionado', $body);
    }

    /** @param array $quote precisa de id/seller_id/total_value/client_name */
    public static function orcamentoRealizado(array $quote): void
    {
        if (empty($quote['seller_id'])) {
            return;
        }

        $body = '<p>Um orçamento foi registrado:</p>'
            . self::orderDetails($quote)
            . self::button(self::BASE_URL . '/painel/orcamentos/' . (int) $quote['id'], 'Ver orçamento no painel');

        self::sendToSellerAndLicenciado((int) $quote['seller_id'], 'Orçamento registrado', 'Orçamento registrado', $body);
    }

    /** @param array $order precisa de id/seller_id/total_value/client_name */
    public static function pedidoRealizado(array $order): void
    {
        if (empty($order['seller_id'])) {
            return;
        }

        $body = '<p>Um pedido foi registrado:</p>'
            . self::orderDetails($order)
            . self::button(self::BASE_URL . '/painel/pedidos/' . (int) $order['id'], 'Ver pedido no painel');

        self::sendToFullChain((int) $order['seller_id'], 'Pedido registrado', 'Pedido registrado', $body);
    }

    /** @param array $order precisa de id/seller_id/total_value/client_name */
    public static function pedidoAprovado(array $order): void
    {
        if (empty($order['seller_id'])) {
            return;
        }

        $body = '<p>Um pedido foi aprovado (pagamento confirmado):</p>'
            . self::orderDetails($order)
            . self::button(self::BASE_URL . '/painel/pedidos/' . (int) $order['id'], 'Ver pedido no painel');

        self::sendToFullChain((int) $order['seller_id'], 'Pedido aprovado', 'Pedido aprovado', $body);
    }

    /** @param array $licenciado precisa de id/name/email */
    public static function cadastroAprovado(array $licenciado): void
    {
        $body = '<p>O cadastro de <strong>' . self::esc($licenciado['name'] ?? '—') . '</strong> como Licenciado Ecodiffusore Brasil foi aprovado.</p>'
            . self::button(self::BASE_URL . '/painel', 'Acessar o painel');

        self::sendToNetworkChain((int) $licenciado['id'], 'Cadastro de Licenciado aprovado', 'Cadastro aprovado', $body);
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
     * Preview do template pra tela de configuracoes (App\Controllers\EmailTemplateSettingsController) --
     * usa um evento de exemplo (Pedido registrado) so pra ilustrar como o layout fica com as
     * configuracoes atuais, sem mandar e-mail nenhum de verdade.
     */
    public static function previewHtml(): string
    {
        $body = '<p>Um pedido foi registrado:</p>'
            . self::infoList(['Cliente' => 'Cliente Exemplo', 'Valor' => 'R$ 2.836,00'])
            . self::button(self::BASE_URL . '/painel/pedidos/1', 'Ver pedido no painel');

        return self::template('Pedido registrado', $body);
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
