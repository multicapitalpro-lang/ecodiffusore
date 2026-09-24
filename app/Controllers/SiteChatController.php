<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\GeoMatch;
use App\Core\Notifier;
use App\Core\Response;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadRoutingSettings;
use App\Models\User;

/** Fase 107: chat proprio no site (widget flutuante, ver layouts/site.php) -- pedido explicito do
 *  usuario pra substituir o antigo botao "chamar no WhatsApp" (que tirava o visitante da pagina na
 *  hora do clique) por uma conversa dentro do proprio site, com o MESMO roteiro de decisao ja
 *  construido pro bot de WhatsApp (Fase 106, App\Core\WhatsAppBot): comprar/orcamento, ja sou
 *  cliente/suporte, sou licenciado/vendedor, falar com atendente. So no passo final (contato
 *  encontrado/roteado) e' que aparece um botao de verdade pro WhatsApp -- o visitante decide
 *  clicar, nunca e' redirecionado sozinho.
 *
 *  Estado da conversa fica na sessao PHP (namespace 'site_chat_*', separado de 'checkout_*' que a
 *  pagina /comprar ja usa -- fluxos independentes de proposito, pra um nunca interferir no outro).
 *  Sem tabela nova: diferente do WhatsApp (onde a conversa e' assincrona, chega por webhook em
 *  qualquer momento, precisa persistir em banco), aqui e' sempre a mesma aba/sessao do navegador
 *  respondendo em sequencia -- a sessao PHP ja resolve. */
class SiteChatController
{
    private const CENTRAL_WHATSAPP = '5545991021551';

    public function message(): void
    {
        $body = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];

        if (!Csrf::verify($body['csrf_token'] ?? null)) {
            Response::json(['ok' => false, 'error' => 'Sessão expirada, recarregue a página.'], 419);
        }

        $step = $body['step'] ?? '';

        switch ($step) {
            case 'contato':
                $this->handleContato($body);
                break;
            case 'opcao':
                $this->handleOpcao($body);
                break;
            case 'cidade':
                $this->handleCidade($body);
                break;
            default:
                Response::json(['ok' => false, 'error' => 'Passo inválido.'], 400);
        }
    }

    private function handleContato(array $body): void
    {
        $name = trim($body['name'] ?? '');
        $whatsapp = preg_replace('/\D/', '', $body['whatsapp'] ?? '');

        if ($name === '' || strlen($whatsapp) < 10) {
            Response::json(['ok' => false, 'error' => 'Preencha nome e um WhatsApp válido (com DDD).']);
        }

        $_SESSION['site_chat_name'] = $name;
        $_SESSION['site_chat_whatsapp'] = $whatsapp;

        Response::json(['ok' => true]);
    }

    private function handleOpcao(array $body): void
    {
        if (empty($_SESSION['site_chat_whatsapp'])) {
            Response::json(['ok' => false, 'error' => 'Sessão expirada, recarregue a página.'], 419);
        }

        $choice = $body['choice'] ?? '';

        switch ($choice) {
            case '1':
                $_SESSION['site_chat_intent'] = 'compra';
                Response::json(['ok' => true, 'next' => 'ask_city']);
                break;

            case '2':
                $existing = Client::findDuplicate(null, $_SESSION['site_chat_whatsapp']);
                if ($existing && !empty($existing['seller_id'])) {
                    $seller = User::find((int) $existing['seller_id']);
                    if ($seller && !empty($seller['whatsapp'])) {
                        Response::json([
                            'ok' => true,
                            'next' => 'final',
                            'message' => "Olá, {$existing['name']}! Seu representante é *{$seller['name']}*. Toque no botão abaixo pra falar direto com ele.",
                            'whatsapp_link' => self::waLink($seller['whatsapp']),
                        ]);
                        return;
                    }
                }
                $_SESSION['site_chat_intent'] = 'suporte';
                Response::json(['ok' => true, 'next' => 'ask_city']);
                break;

            case '3':
                Response::json([
                    'ok' => true,
                    'next' => 'final',
                    'message' => 'Beleza! Encaminhando você pro nosso time interno de suporte.',
                    'whatsapp_link' => self::waLink(self::CENTRAL_WHATSAPP),
                ]);
                break;

            case '4':
                Response::json([
                    'ok' => true,
                    'next' => 'final',
                    'message' => 'Ok! Toque no botão abaixo pra falar com nossa equipe agora.',
                    'whatsapp_link' => self::waLink(self::CENTRAL_WHATSAPP),
                ]);
                break;

            default:
                Response::json(['ok' => true, 'next' => 'invalid']);
        }
    }

    private function handleCidade(array $body): void
    {
        $intent = $_SESSION['site_chat_intent'] ?? null;
        if (!$intent || empty($_SESSION['site_chat_whatsapp'])) {
            Response::json(['ok' => false, 'error' => 'Sessão expirada, recarregue a página.'], 419);
        }

        $city = trim($body['city'] ?? '');
        if ($city === '' || mb_strlen($city) < 2) {
            Response::json(['ok' => false, 'error' => 'Selecione uma cidade válida da lista.']);
        }

        $name = $_SESSION['site_chat_name'];
        $whatsapp = $_SESSION['site_chat_whatsapp'];
        $seller = GeoMatch::nearestSeller($city);

        if ($intent === 'compra') {
            $ownerId = $seller['id'] ?? LeadRoutingSettings::centralLicenciadoId();
            $leadId = Lead::create([
                'name' => $name,
                'whatsapp' => $whatsapp,
                'city' => $city,
                'source' => 'site_chat',
            ]);
            if ($ownerId) {
                Lead::assignTo($leadId, $ownerId);
                Notifier::leadRoteado(['name' => $name, 'whatsapp' => $whatsapp, 'city' => $city], $ownerId);
            }
        }

        if ($seller) {
            Response::json([
                'ok' => true,
                'next' => 'final',
                'message' => "Encontrei! O representante da sua região é *{$seller['name']}*. Toque no botão abaixo pra falar direto com ele.",
                'whatsapp_link' => self::waLink($seller['whatsapp']),
            ]);
        } else {
            Response::json([
                'ok' => true,
                'next' => 'final',
                'message' => 'Registrei seu contato! Um de nossos representantes vai falar com você em breve. Se preferir, já toque no botão abaixo pra adiantar.',
                'whatsapp_link' => self::waLink(self::CENTRAL_WHATSAPP),
            ]);
        }
    }

    private static function waLink(string $whatsapp): string
    {
        $digits = preg_replace('/\D/', '', $whatsapp);
        if (strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }
        return 'https://wa.me/' . $digits;
    }
}
