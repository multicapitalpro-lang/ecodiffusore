<?php

namespace App\Core;

use App\Models\User;

/**
 * Central de notificacoes por e-mail pros eventos que o Vendedor e o Licenciado da rede dele
 * precisam acompanhar (lead roteado, orcamento/pedido registrado, pedido aprovado) + cadastro de
 * Licenciado aprovado. Um metodo por evento -- cada um resolve os destinatarios (vendedor
 * responsavel + licenciado da rede dele, sem duplicar e-mail se forem a mesma pessoa, ex: lead
 * caido direto no Licenciado Central por falta de vendedor no raio) e monta o corpo do e-mail.
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
            . '<ul>'
            . '<li>Nome: ' . self::esc($lead['name'] ?? '—') . '</li>'
            . '<li>WhatsApp: ' . self::esc($lead['whatsapp'] ?? '—') . '</li>'
            . '<li>Cidade: ' . self::esc($lead['city'] ?? '—') . '</li>'
            . '</ul>'
            . '<p><a href="' . self::BASE_URL . '/painel/leads">Acessar Leads no painel</a></p>';

        self::sendToSellerAndLicenciado($assigneeId, 'Novo lead direcionado a você', $body);
    }

    /** @param array $quote precisa de id/seller_id/total_value/client_name */
    public static function orcamentoRealizado(array $quote): void
    {
        if (empty($quote['seller_id'])) {
            return;
        }

        $body = '<p>Um orçamento foi registrado:</p>'
            . self::detailsList($quote)
            . '<p><a href="' . self::BASE_URL . '/painel/orcamentos/' . (int) $quote['id'] . '">Ver orçamento no painel</a></p>';

        self::sendToSellerAndLicenciado((int) $quote['seller_id'], 'Orçamento registrado', $body);
    }

    /** @param array $order precisa de id/seller_id/total_value/client_name */
    public static function pedidoRealizado(array $order): void
    {
        if (empty($order['seller_id'])) {
            return;
        }

        $body = '<p>Um pedido foi registrado:</p>'
            . self::detailsList($order)
            . '<p><a href="' . self::BASE_URL . '/painel/pedidos/' . (int) $order['id'] . '">Ver pedido no painel</a></p>';

        self::sendToSellerAndLicenciado((int) $order['seller_id'], 'Pedido registrado', $body);
    }

    /** @param array $order precisa de id/seller_id/total_value/client_name */
    public static function pedidoAprovado(array $order): void
    {
        if (empty($order['seller_id'])) {
            return;
        }

        $body = '<p>Um pedido foi aprovado (pagamento confirmado):</p>'
            . self::detailsList($order)
            . '<p><a href="' . self::BASE_URL . '/painel/pedidos/' . (int) $order['id'] . '">Ver pedido no painel</a></p>';

        self::sendToSellerAndLicenciado((int) $order['seller_id'], 'Pedido aprovado', $body);
    }

    /** @param array $licenciado precisa de name/email */
    public static function cadastroAprovado(array $licenciado): void
    {
        if (empty($licenciado['email'])) {
            return;
        }

        Mailer::send(
            $licenciado['email'],
            'Cadastro aprovado - Ecodiffusore Brasil',
            '<p>Olá, ' . self::esc($licenciado['name']) . '!</p>'
            . '<p>Seu cadastro como Licenciado Ecodiffusore Brasil foi aprovado. Você já pode acessar o painel normalmente.</p>'
            . '<p><a href="' . self::BASE_URL . '/painel">Acessar o painel</a></p>'
        );
    }

    private static function detailsList(array $row): string
    {
        return '<ul>'
            . '<li>Cliente: ' . self::esc($row['client_name'] ?? '—') . '</li>'
            . '<li>Valor: R$ ' . number_format((float) ($row['total_value'] ?? 0), 2, ',', '.') . '</li>'
            . '</ul>';
    }

    private static function sendToSellerAndLicenciado(int $sellerId, string $subject, string $body): void
    {
        $seller = User::find($sellerId);
        if (!$seller) {
            return;
        }

        $greeting = '<p>Olá, ' . self::esc($seller['name']) . '!</p>';

        $recipients = [];
        if (!empty($seller['email'])) {
            $recipients[(int) $seller['id']] = $seller['email'];
        }

        $licenciado = User::licenciadoFor($sellerId);
        if ($licenciado && !empty($licenciado['email'])) {
            $recipients[(int) $licenciado['id']] = $licenciado['email'];
        }

        foreach ($recipients as $email) {
            Mailer::send($email, $subject . ' - Ecodiffusore Brasil', $greeting . $body);
        }
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
