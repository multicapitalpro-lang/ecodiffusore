<?php

namespace App\Core;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadRoutingSettings;
use App\Models\User;
use App\Models\WhatsAppBotSession;
use App\Models\WhatsAppChat;

/** Fase 106: menu de atendimento automatico no numero principal do WhatsApp do site -- cliente
 *  manda mensagem, escolhe uma opcao numerada (Evolution/Baileys nao tem mensagem de lista/botao
 *  interativo aqui, ver EvolutionApiClient -- so' texto puro, entao o menu e' sempre "digite o
 *  numero"), e o bot roteia pro Licenciado/Vendedor da regiao (reaproveita App\Core\GeoMatch, o
 *  MESMO calculo ja usado em /comprar) ou entrega direto pro atendimento humano.
 *
 *  Decisao explicita do usuario: o bot so' ENTREGA o contato e encerra (nunca mantem a conversa
 *  nem tenta responder pergunta livre) -- pra falar de verdade com humano, digite 4 ou "menu" a
 *  qualquer momento reinicia do zero. Chamado por WhatsAppWebhookController quando a mensagem
 *  chega na instancia CENTRAL (WhatsAppInstance::central()), nunca nas pessoais de Licenciado/
 *  Gestor/Vendedor (essas continuam 100% manuais, sem bot). */
class WhatsAppBot
{
    /** Estados em que o bot fica calado ate' a pessoa digitar uma palavra de reinicio -- ja foi
     *  entregue pro humano/pro contato certo, insistir de novo so' atrapalha. */
    private const SILENT_STATES = ['atendimento_humano', 'finalizado'];
    private const RESET_WORDS = ['menu', 'oi', 'ola', 'olá', 'inicio', 'início', 'começar', 'comecar'];

    public static function handleIncoming(array $instance, int $chatId, string $remoteJid, string $text, ?string $pushName): void
    {
        if (empty($instance['bot_enabled']) || str_ends_with($remoteJid, '@g.us')) {
            return;
        }

        $client = new EvolutionApiClient($instance['instance_name']);
        $normalized = self::normalize($text);
        $session = WhatsAppBotSession::forJid($remoteJid);

        if (!$session || in_array($normalized, self::RESET_WORDS, true)) {
            WhatsAppBotSession::start($remoteJid);
            self::sendMainMenu($client, $remoteJid);
            return;
        }

        if (in_array($session['state'], self::SILENT_STATES, true)) {
            return;
        }

        switch ($session['state']) {
            case 'aguardando_cidade_compra':
                self::handleCidade($client, $remoteJid, $chatId, $text, $pushName, true);
                break;
            case 'aguardando_cidade_suporte':
                self::handleCidade($client, $remoteJid, $chatId, $text, $pushName, false);
                break;
            case 'menu_principal':
            default:
                self::handleMenuChoice($client, $remoteJid, $chatId, $normalized, $pushName);
                break;
        }
    }

    private static function sendMainMenu(EvolutionApiClient $client, string $jid): void
    {
        self::reply($client, $jid,
            "🌱 Olá! Bem-vindo(a) à *Ecodiffusore Brasil*.\n\n" .
            "Escolha uma opção digitando o número:\n\n" .
            "1️⃣ Quero comprar / pedir um orçamento\n" .
            "2️⃣ Já sou cliente — suporte / pós-venda\n" .
            "3️⃣ Sou Licenciado ou Vendedor — outro assunto\n" .
            "4️⃣ Falar com um atendente\n\n" .
            "_A qualquer momento, digite *menu* pra voltar aqui._"
        );
    }

    private static function handleMenuChoice(EvolutionApiClient $client, string $jid, int $chatId, string $choice, ?string $pushName): void
    {
        switch ($choice) {
            case '1':
                WhatsAppBotSession::updateState($jid, 'aguardando_cidade_compra');
                self::reply($client, $jid, 'Perfeito! Me diga o *nome da sua cidade* pra eu te conectar com o representante mais próximo. 📍');
                break;
            case '2':
                self::handleSuporteInicio($client, $jid, $chatId);
                break;
            case '3':
                self::reply($client, $jid, "Beleza! Encaminhando você pro nosso time interno de suporte -- alguém vai te responder por aqui em breve.");
                WhatsAppBotSession::updateState($jid, 'atendimento_humano');
                break;
            case '4':
                self::reply($client, $jid, 'Ok! Em breve alguém da nossa equipe vai te responder por aqui. 🙋');
                WhatsAppBotSession::updateState($jid, 'atendimento_humano');
                break;
            default:
                self::reply($client, $jid, 'Não entendi 🤔 Digite o número de uma das opções abaixo:');
                self::sendMainMenu($client, $jid);
                break;
        }
    }

    /** Opcao 2 -- antes de pedir cidade, tenta achar quem o cliente ja e' (WhatsApp ja cadastrado
     *  em algum Cliente) pra rotear direto pro vendedor responsavel, sem pergunta extra. */
    private static function handleSuporteInicio(EvolutionApiClient $client, string $jid, int $chatId): void
    {
        $phone = self::phoneFromJid($jid);
        $existing = Client::findDuplicate(null, $phone);

        if ($existing && !empty($existing['seller_id'])) {
            $seller = User::find((int) $existing['seller_id']);
            if ($seller && !empty($seller['whatsapp'])) {
                self::reply($client, $jid,
                    "Olá, {$existing['name']}! Seu representante é *{$seller['name']}*, fala direto com ele:\n" .
                    self::waLink($seller['whatsapp']) .
                    "\n\nSe preferir, digite *4* pra falar com nosso atendimento."
                );
                WhatsAppBotSession::updateState($jid, 'finalizado');
                return;
            }
        }

        WhatsAppBotSession::updateState($jid, 'aguardando_cidade_suporte');
        self::reply($client, $jid, 'Me diga o *nome da sua cidade* pra eu te conectar com quem pode te ajudar. 📍');
    }

    private static function handleCidade(EvolutionApiClient $client, string $jid, int $chatId, string $cityRaw, ?string $pushName, bool $isNewSale): void
    {
        $city = trim($cityRaw);
        if ($city === '' || mb_strlen($city) < 2) {
            self::reply($client, $jid, 'Não entendi a cidade, pode digitar de novo? (só o nome, ex: "Cascavel")');
            return;
        }

        $seller = GeoMatch::nearestSeller($city);
        $phone = self::phoneFromJid($jid);

        if ($isNewSale) {
            $name = $pushName ?: 'Contato WhatsApp';
            $ownerId = $seller['id'] ?? LeadRoutingSettings::centralLicenciadoId();

            $leadId = Lead::create([
                'name' => $name,
                'whatsapp' => $phone,
                'city' => $city,
                'source' => 'whatsapp_bot',
            ]);
            if ($ownerId) {
                Lead::assignTo($leadId, $ownerId);
                Notifier::leadRoteado(['name' => $name, 'whatsapp' => $phone, 'city' => $city], $ownerId);
            }
            WhatsAppChat::linkLead($chatId, $leadId);
            WhatsAppBotSession::updateState($jid, $seller ? 'finalizado' : 'atendimento_humano', $leadId);
        } else {
            WhatsAppBotSession::updateState($jid, $seller ? 'finalizado' : 'atendimento_humano');
        }

        if ($seller) {
            self::reply($client, $jid,
                "Encontrei! O representante da sua região é *{$seller['name']}*.\n" .
                "Fala direto com ele: " . self::waLink($seller['whatsapp'])
            );
        } else {
            self::reply($client, $jid, 'Registrei seu contato! Um de nossos representantes vai falar com você em breve por aqui. 🙌');
        }
    }

    private static function reply(EvolutionApiClient $client, string $jid, string $text): void
    {
        try {
            $client->sendText($jid, $text);
        } catch (\Throwable $e) {
            error_log('WhatsAppBot: falha ao responder ' . $jid . ': ' . $e->getMessage());
        }
    }

    private static function waLink(string $whatsapp): string
    {
        $digits = preg_replace('/\D/', '', $whatsapp);
        return 'https://wa.me/55' . $digits;
    }

    /** "554599998888@s.whatsapp.net" -> "4599998888" (mesmo formato sem "55" ja usado em
     *  clients.whatsapp/users.whatsapp em todo o resto do sistema). */
    private static function phoneFromJid(string $jid): string
    {
        $digits = preg_replace('/\D/', '', explode('@', $jid)[0]);
        if (strlen($digits) > 11 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }
        return $digits;
    }

    private static function normalize(string $text): string
    {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', trim($text)) ?: trim($text);
        return mb_strtolower($transliterated);
    }
}
