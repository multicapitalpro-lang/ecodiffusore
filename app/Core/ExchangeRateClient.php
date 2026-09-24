<?php

namespace App\Core;

use App\Models\ExchangeRate;

/** Fase 111: cotacao Real -> Guarani paraguaio (BRL->PYG), usada nas calculadoras de economia/
 *  comissao pro Licenciado (e rede) que vai atuar no Paraguai (users.secondary_currency). Duas
 *  fontes publicas/sem chave, tentadas em ordem (open.er-api.com primeiro -- AwesomeAPI, tentada
 *  na Fase 111, devolveu 429 QuotaExceeded ao vivo no IP do servidor, mesmo sem uso nenhum antes;
 *  mantida como segunda tentativa caso volte a funcionar). Cache em banco (12h) pra nao bater na
 *  API a cada carga de tela. Se as duas falharem e nunca teve cache nenhum, cai num valor
 *  aproximado fixo -- nunca trava a ferramenta por causa de API externa fora do ar. */
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

        foreach ([[self::class, 'fetchFromErApi'], [self::class, 'fetchFromAwesomeApi']] as $fetcher) {
            try {
                $rate = $fetcher();
                ExchangeRate::upsert(self::PAIR, $rate);
                return $rate;
            } catch (\Throwable $e) {
                error_log('ExchangeRateClient: falha ao buscar cotação BRL->PYG (' . $fetcher[1] . '): ' . $e->getMessage());
            }
        }

        return $cached ? (float) $cached['rate'] : self::FALLBACK_RATE;
    }

    /** open.er-api.com -- free, sem chave, sem limite documentado agressivo, atualiza 1x/dia. */
    private static function fetchFromErApi(): float
    {
        $response = self::curlGet('https://open.er-api.com/v6/latest/BRL');
        $data = json_decode($response, true);

        if (($data['result'] ?? null) !== 'success') {
            throw new \RuntimeException('Resposta inválida da API de câmbio (open.er-api.com).');
        }

        $rate = $data['rates']['PYG'] ?? null;
        if (!$rate || !is_numeric($rate)) {
            throw new \RuntimeException('PYG ausente na resposta da API de câmbio (open.er-api.com).');
        }

        return (float) $rate;
    }

    /** economia.awesomeapi.com.br -- publica/brasileira, sem chave -- fallback, ver docblock. */
    private static function fetchFromAwesomeApi(): float
    {
        $response = self::curlGet('https://economia.awesomeapi.com.br/json/last/BRL-PYG');
        $data = json_decode($response, true);

        $bid = $data['BRLPYG']['bid'] ?? null;
        if (!$bid || !is_numeric($bid)) {
            throw new \RuntimeException('Resposta inválida da API de câmbio (AwesomeAPI).');
        }

        return (float) $bid;
    }

    private static function curlGet(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 6,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $error) {
            throw new \RuntimeException('Erro de conexão com a API de câmbio: ' . $error);
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException('API de câmbio retornou HTTP ' . $httpCode . ': ' . substr($response, 0, 200));
        }

        return $response;
    }
}
