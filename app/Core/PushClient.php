<?php

namespace App\Core;

/**
 * Fase 77: push notification real via Expo Push Service -- primeiro canal de push do sistema,
 * pensado pra ir gradualmente substituindo o WhatsApp (motivacao original do app mobile). Usa o
 * servico gratuito da Expo (exp.host), que ja fala com APNs (iOS) e FCM (Android) por baixo dos
 * panos -- nao precisa de certificado Apple/chave Firebase proprios enquanto o app roda via Expo
 * Go/desenvolvimento; isso so entra na hora de gerar o build final pra loja (EAS Build), sem
 * mudar nada deste cliente.
 */
class PushClient
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /** Fase 91: $priority separa "urgente" (com som, precisa de acao/atencao ja) de "normal"
     *  (silencioso, so' pra ficar sabendo) -- ate aqui todo push tocava som igual, sem
     *  diferenciar o que realmente precisa interromper a pessoa. channelId bate com os canais
     *  Android registrados no app (src/push.ts, 'urgent'/'normal'); no iOS quem decide
     *  som/critico e' o campo sound em si (Expo nao usa channelId no iOS).
     * @param string[] $tokens */
    public static function send(array $tokens, string $title, string $body, array $data = [], string $priority = 'urgent'): void
    {
        $tokens = array_values(array_filter($tokens, fn ($t) => str_starts_with($t, 'ExponentPushToken')));
        if (!$tokens) {
            return;
        }

        $isUrgent = $priority === 'urgent';
        $messages = array_map(fn ($t) => [
            'to' => $t,
            'sound' => $isUrgent ? 'default' : null,
            'priority' => $isUrgent ? 'high' : 'default',
            'channelId' => $isUrgent ? 'urgent' : 'normal',
            'title' => $title,
            'body' => $body,
            'data' => $data + ['priority' => $priority],
        ], $tokens);

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Accept-Encoding: gzip, deflate',
            ],
            CURLOPT_POSTFIELDS => json_encode($messages),
            CURLOPT_TIMEOUT => 8,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('Push dispatch falhou (transporte): ' . $error);
            return;
        }

        // Best-effort: so registra no log se a Expo sinalizar erro por mensagem (ex: token
        // invalido/desregistrado) -- nao interrompe o restante do fluxo que chamou isso.
        $decoded = json_decode((string) $response, true);
        if (is_array($decoded['data'] ?? null)) {
            foreach ($decoded['data'] as $result) {
                if (($result['status'] ?? null) === 'error') {
                    error_log('Push rejeitado pela Expo: ' . ($result['message'] ?? 'sem detalhe'));
                }
            }
        }
    }
}
