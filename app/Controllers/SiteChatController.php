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
 *  Fase 109: a cidade passou a ser pedida JUNTO com nome/WhatsApp, logo no primeiro passo (antes
 *  so' era pedida se a pessoa escolhesse "quero comprar") -- pedido explicito do usuario: TODO
 *  contato que chega pelo chat e ainda nao esta em nenhum CRM (nunca foi Licenciado/Gestor/
 *  Vendedor nem virou Cliente) precisa ser roteado igual ao popup de /comprar (GeoMatch::
 *  nearestSeller(), raio de 100km, fallback pro Licenciado central) e entrar no CRM de algum
 *  vendedor na hora -- nao so' quando ela clica em "quero comprar". Quem ja e' conhecido (cliente
 *  ou da propria equipe) nao gera Lead duplicado.
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
            default:
                Response::json(['ok' => false, 'error' => 'Passo inválido.'], 400);
        }
    }

    /** Nome + WhatsApp + Cidade, tudo de uma vez -- ja' roteia por regiao aqui mesmo, antes de
     *  mostrar o menu, pra garantir que ninguem passa pelo chat sem ficar registrado no CRM de
     *  algum vendedor (ou do Licenciado central, se ninguem estiver no raio de 100km). */
    private function handleContato(array $body): void
    {
        $name = trim($body['name'] ?? '');
        $whatsapp = preg_replace('/\D/', '', $body['whatsapp'] ?? '');
        $city = trim($body['city'] ?? '');

        if ($name === '' || strlen($whatsapp) < 10) {
            Response::json(['ok' => false, 'error' => 'Preencha nome e um WhatsApp válido (com DDD).']);
        }
        if ($city === '' || mb_strlen($city) < 2) {
            Response::json(['ok' => false, 'error' => 'Selecione uma cidade válida da lista.']);
        }

        $_SESSION['site_chat_name'] = $name;
        $_SESSION['site_chat_whatsapp'] = $whatsapp;
        $_SESSION['site_chat_city'] = $city;
        $_SESSION['site_chat_seller'] = $this->resolveAndRouteContact($name, $whatsapp, $city);

        Response::json(['ok' => true]);
    }

    /** Se o WhatsApp ja pertence a um Cliente com vendedor -- usa o vendedor dele direto (nunca
     *  cria Lead pra quem ja e' cliente). Se ja pertence a um Licenciado/Gestor/Vendedor da propria
     *  equipe -- nao e' Lead nenhum, so nao roteia (fica null, cai no atendimento central se
     *  precisar). So' quando NENHum dos dois bate e' que roda o mesmo GeoMatch::nearestSeller() do
     *  popup de /comprar, cria (ou reaproveita, se ja existia de uma visita anterior) o Lead e
     *  garante que ele fica atribuido a alguem.
     *  @return array{id:int,name:string,whatsapp:string}|null */
    private function resolveAndRouteContact(string $name, string $whatsapp, string $city): ?array
    {
        $existingClient = Client::findDuplicate(null, $whatsapp);
        if ($existingClient) {
            if (!empty($existingClient['seller_id'])) {
                $seller = User::find((int) $existingClient['seller_id']);
                if ($seller && !empty($seller['whatsapp'])) {
                    return ['id' => (int) $seller['id'], 'name' => $seller['name'], 'whatsapp' => $seller['whatsapp']];
                }
            }
            return null;
        }

        if (User::findByWhatsapp($whatsapp)) {
            // Ja e' Licenciado/Gestor/Vendedor -- nao e' lead, nao roteia por geolocalizacao.
            return null;
        }

        $geo = GeoMatch::nearestSeller($city);
        $ownerId = $geo['id'] ?? LeadRoutingSettings::centralLicenciadoId();

        $existingLead = Lead::findByWhatsapp($whatsapp);
        if ($existingLead) {
            if (empty($existingLead['assigned_to_user_id']) && $ownerId) {
                Lead::assignTo((int) $existingLead['id'], $ownerId);
                Notifier::leadRoteado(['name' => $name, 'whatsapp' => $whatsapp, 'city' => $city], $ownerId);
            }
        } else {
            $leadId = Lead::create(['name' => $name, 'whatsapp' => $whatsapp, 'city' => $city, 'source' => 'site_chat']);
            if ($ownerId) {
                Lead::assignTo($leadId, $ownerId);
                Notifier::leadRoteado(['name' => $name, 'whatsapp' => $whatsapp, 'city' => $city], $ownerId);
            }
        }

        return $geo; // null quando ninguem esta no raio de 100km -- cai no fallback central.
    }

    private function handleOpcao(array $body): void
    {
        if (empty($_SESSION['site_chat_whatsapp'])) {
            Response::json(['ok' => false, 'error' => 'Sessão expirada, recarregue a página.'], 419);
        }

        $choice = $body['choice'] ?? '';
        $seller = $_SESSION['site_chat_seller'] ?? null;
        $name = $_SESSION['site_chat_name'] ?? '';
        $city = $_SESSION['site_chat_city'] ?? '';

        switch ($choice) {
            case '1':
            case '2':
                // Fase 110: mensagem pre-preenchida no WhatsApp -- pedido explicito do usuario, quem
                // recebe (o vendedor/Licenciado roteado, ou o atendimento central) precisa ja saber
                // quem e' o contato e o motivo, sem depender do cliente digitar tudo de novo.
                $intro = $choice === '1'
                    ? "Olá! Meu nome é {$name}, sou de {$city} e vim pelo chat do site. Tenho interesse em economizar combustível com o Ecodiffusore e gostaria de um orçamento."
                    : "Olá! Meu nome é {$name}, sou de {$city} e vim pelo chat do site. Já sou cliente e preciso de suporte.";
                if ($seller) {
                    Response::json([
                        'ok' => true,
                        'next' => 'final',
                        'message' => "Encontrei! O representante da sua região é *{$seller['name']}*. Toque no botão abaixo pra falar direto com ele.",
                        'whatsapp_link' => self::waLink($seller['whatsapp'], $intro),
                    ]);
                } else {
                    Response::json([
                        'ok' => true,
                        'next' => 'final',
                        'message' => 'Registrei seu contato! Um de nossos representantes vai falar com você em breve. Se preferir, já toque no botão abaixo pra adiantar.',
                        'whatsapp_link' => self::waLink(self::CENTRAL_WHATSAPP, $intro),
                    ]);
                }
                break;

            case '3':
                Response::json([
                    'ok' => true,
                    'next' => 'final',
                    'message' => 'Beleza! Encaminhando você pro nosso time interno de suporte.',
                    'whatsapp_link' => self::waLink(self::CENTRAL_WHATSAPP, "Olá! Meu nome é {$name}. Faço parte da equipe (Licenciado/Vendedor) e preciso falar com o time interno."),
                ]);
                break;

            case '4':
                Response::json([
                    'ok' => true,
                    'next' => 'final',
                    'message' => 'Ok! Toque no botão abaixo pra falar com nossa equipe agora.',
                    'whatsapp_link' => self::waLink(self::CENTRAL_WHATSAPP, "Olá! Meu nome é {$name}, sou de {$city} e vim pelo chat do site. Gostaria de falar com um atendente."),
                ]);
                break;

            default:
                Response::json(['ok' => true, 'next' => 'invalid']);
        }
    }

    private static function waLink(string $whatsapp, string $text = ''): string
    {
        $digits = preg_replace('/\D/', '', $whatsapp);
        if (strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }
        $link = 'https://wa.me/' . $digits;
        return $text !== '' ? $link . '?text=' . rawurlencode($text) : $link;
    }
}
