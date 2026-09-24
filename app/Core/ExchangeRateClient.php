<?php

namespace App\Core;

use App\Models\ExchangeRate;

/** Fase 111: cotacao Real -> Guarani paraguaio (BRL->PYG), usada nas calculadoras de economia/
 *  comissao pro Licenciado (e rede) que vai atuar no Paraguai (users.secondary_currency). Busca
 *  na AwesomeAPI (economia.awesomeapi.com.br, publica, brasileira, sem chave -- ja' amplamente
 *  usada em projetos brasileiros pra cotacao de moeda), com cache em banco (12h) pra nao bater na
 *  API a cada carga de tela. Se a API falhar e nunca teve cache nenhum, cai num valor aproximado
 *  fixo -- nunca trava a ferramenta por causa de uma API externa fora do ar. */
class ExchangeRateClient
{
    private const PAIR = 'BRL_PYG';
    private const CACHE_HOURS = 12;
    private const FALLBACK_RATE = 1500.0;

    public static function brlToPyg(): float
    {
        $cached = ExchangeRate::find(self::PAIR);
        if ($cached && strtotime($cached['fetched_at']) > strtotime('-' . self::CACHE_HOURS . ' hours')) {
            return (float) $cached['rate'];
        }

        try {
            $rate = self::fetchFromApi();
            ExchangeRate::upsert(self::PAIR, $rate);
            return $rate;
        } catch (\Throwable $e) {
            error_log('ExchangeRateClient: falha ao buscar cotação BRL->PYG: ' . $e->getMessage());
            return $cached ? (float) $cached['rate'] : self::FALLBACK_RATE;
        }
    }

    private static function fetchFromApi(): float
    {
        $ch = curl_init('https://economia.awesomeapi.com.br/json/last/BRL-PYG');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 6,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error) {
            throw new \RuntimeException('Erro de conexão com a API de câmbio: ' . $error);
        }

        $data = json_decode($response, true);
        $bid = $data['BRLPYG']['bid'] ?? null;
        if (!$bid || !is_numeric($bid)) {
            throw new \RuntimeException('Resposta inválida da API de câmbio.');
        }

        return (float) $bid;
    }
}
