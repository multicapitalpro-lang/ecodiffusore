<?php

namespace App\Core;

/**
 * Rastreio real via API oficial dos Correios (https://api.correios.com.br) -- precisa de um
 * contrato Correios (numero do Cartao de Postagem + usuario/senha do Meu Correios Business,
 * cadastrados em 'correios' no config.php). SEM esse contrato, track() sempre devolve null (mesmo
 * espirito de stub do App\Core\DataflowClient: estrutura pronta, sem chamada de verdade enquanto
 * nao houver credencial). O endpoint publico nao-oficial (proxyapp.correios.com.br) foi tentado e
 * bloqueia qualquer IP de datacenter/cloud com 403 -- so a API oficial autenticada funciona pra
 * uso automatizado server-to-server.
 */
class CorreiosClient
{
    private string $usuario;
    private string $senha;
    private string $cartaoPostagem;

    public function __construct()
    {
        $config = Config::get('correios', []);
        $this->usuario = $config['usuario'] ?? '';
        $this->senha = $config['senha'] ?? '';
        $this->cartaoPostagem = $config['cartao_postagem'] ?? '';
    }

    /**
     * @return array{status:?string,date:?string,entregue:bool}|null null quando: sem credencial
     *  configurada, codigo nao e' formato Correios (outra transportadora), ou a API falhou/nao
     *  achou o objeto -- nunca lanca excecao (best-effort, mesmo padrao do NfeStatusChecker).
     */
    public function track(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z]{2}\d{9}BR$/', $code)) {
            return null;
        }

        try {
            $token = $this->authenticate();
            if (!$token) {
                return null;
            }

            $result = $this->request('GET', "/srorastro/v1/objetos/{$code}", ['resultado' => 'U'], $token);
            $eventos = $result['objetos'][0]['eventos'] ?? [];
            if (!$eventos) {
                return null;
            }

            $ultimo = $eventos[0];
            $descricao = trim(($ultimo['descricao'] ?? '') . (!empty($ultimo['unidade']['nome']) ? ' — ' . $ultimo['unidade']['nome'] : ''));

            return [
                'status' => $descricao !== '' ? $descricao : null,
                'date' => $ultimo['dtHrCriado'] ?? null,
                'entregue' => stripos($descricao, 'entregue') !== false,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function authenticate(): ?string
    {
        if ($this->usuario === '' || $this->senha === '') {
            return null;
        }

        $ch = curl_init('https://api.correios.com.br/token/v1/autentica/cartaopostagem');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->usuario . ':' . $this->senha),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['numero' => $this->cartaoPostagem]),
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode((string) $response, true);
        return $decoded['token'] ?? null;
    }

    private function request(string $method, string $path, array $params, string $token): array
    {
        $url = 'https://api.correios.com.br' . $path;
        if ($method === 'GET' && $params) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Erro de conexão com os Correios: ' . $error);
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }
}
